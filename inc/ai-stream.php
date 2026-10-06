<?php
/**
 * Streaming transport for the OpenAI-compatible JLuxe assistant providers.
 * The API key stays server-side; only text deltas and the final safe product
 * summaries are sent to the browser as same-origin Server-Sent Events.
 */

defined( 'ABSPATH' ) || exit;

function jluxe_ai_sse_send_event( string $event, array $data = array() ): void {
	$event = preg_replace( '/[^a-z0-9_-]/i', '', $event );
	$json  = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	echo 'event: ' . $event . "\n";
	echo 'data: ' . ( is_string( $json ) ? $json : '{}' ) . "\n\n";
	if ( ob_get_level() > 0 ) {
		@ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	flush();
}

function jluxe_ai_sse_begin(): void {
	// Remove PHP/WP output buffers that would otherwise hold every event until the end.
	while ( ob_get_level() > 0 ) {
		$level = ob_get_level();
		@ob_end_clean(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ob_get_level() >= $level ) {
			break;
		}
	}
	@ini_set( 'zlib.output_compression', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	@ini_set( 'output_buffering', 'off' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	if ( ! headers_sent() ) {
		header_remove( 'Content-Type' );
		header_remove( 'Content-Length' );
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, no-transform' );
		header( 'X-Accel-Buffering: no' );
		header( 'X-Content-Type-Options: nosniff' );
	}
	echo "retry: 2000\n\n";
	flush();
}

/** Incrementally decode SSE frames, including CRLF splits across cURL chunks. */
function jluxe_ai_sse_parse_chunk( string $chunk, array &$state, callable $on_event, bool $final = false ): void {
	if ( ! isset( $state['buffer'], $state['event'], $state['data'], $state['pending_cr'] ) ) {
		$state = array( 'buffer' => '', 'event' => '', 'data' => array(), 'pending_cr' => false );
	}
	if ( $state['pending_cr'] ) {
		$chunk               = "\r" . $chunk;
		$state['pending_cr'] = false;
	}
	$buffer = $state['buffer'] . $chunk;
	if ( ! $final && '' !== $buffer && "\r" === substr( $buffer, -1 ) ) {
		$state['pending_cr'] = true;
		$buffer              = substr( $buffer, 0, -1 );
	}
	$buffer = str_replace( array( "\r\n", "\r" ), "\n", $buffer );

	$consume_line = static function ( string $line ) use ( &$state, $on_event ): void {
		if ( '' === $line ) {
			if ( ! empty( $state['data'] ) ) {
				$on_event( '' !== $state['event'] ? $state['event'] : 'message', implode( "\n", $state['data'] ) );
			}
			$state['event'] = '';
			$state['data']  = array();
			return;
		}
		if ( ':' === substr( $line, 0, 1 ) ) {
			return; // SSE comment / keep-alive.
		}
		$separator = strpos( $line, ':' );
		$field     = false === $separator ? $line : substr( $line, 0, $separator );
		$value     = false === $separator ? '' : substr( $line, $separator + 1 );
		if ( ' ' === substr( $value, 0, 1 ) ) {
			$value = substr( $value, 1 );
		}
		if ( 'event' === $field ) {
			$state['event'] = $value;
		} elseif ( 'data' === $field ) {
			$state['data'][] = $value;
		}
	};

	while ( false !== ( $newline = strpos( $buffer, "\n" ) ) ) {
		$consume_line( substr( $buffer, 0, $newline ) );
		$buffer = substr( $buffer, $newline + 1 );
	}
	$state['buffer'] = $buffer;

	if ( $final ) {
		if ( '' !== $state['buffer'] ) {
			$consume_line( $state['buffer'] );
			$state['buffer'] = '';
		}
		$consume_line( '' );
		$state['pending_cr'] = false;
	}
}

/**
 * POST through cURL's write callback so parsed provider deltas can be flushed
 * to the REST client immediately. The filter is also a test seam for hosts
 * whose PHP build intentionally has no cURL extension.
 */
function jluxe_ai_stream_http_post( string $url, array $headers, string $body, callable $on_event ) {
	$parser_state = array( 'buffer' => '', 'event' => '', 'data' => array(), 'pending_cr' => false );
	$mock         = apply_filters( 'jluxe_ai_stream_http_response', null, $url, $headers, $body );
	if ( is_wp_error( $mock ) ) {
		return $mock;
	}
	if ( is_array( $mock ) && array_key_exists( 'status', $mock ) && isset( $mock['chunks'] ) && is_array( $mock['chunks'] ) ) {
		$raw_body = '';
		foreach ( $mock['chunks'] as $chunk ) {
			$chunk     = (string) $chunk;
			$raw_body .= $chunk;
			if ( (int) $mock['status'] < 400 ) {
				jluxe_ai_sse_parse_chunk( $chunk, $parser_state, $on_event );
			}
		}
		if ( (int) $mock['status'] < 400 ) {
			jluxe_ai_sse_parse_chunk( '', $parser_state, $on_event, true );
		}
		if ( '' === $raw_body && isset( $mock['body'] ) ) {
			$raw_body = (string) $mock['body'];
		}
		return array(
			'status' => (int) $mock['status'],
			'error'  => (string) ( $mock['error'] ?? '' ),
			'body'   => $raw_body,
		);
	}

	if ( ! function_exists( 'curl_init' ) || ! defined( 'CURLOPT_WRITEFUNCTION' ) ) {
		return new WP_Error( 'jluxe_ai_stream_unavailable', 'ارتباطِ stream در این سرور در دسترس نیست.', array( 'status' => 501 ) );
	}
	$handle = curl_init( $url );
	if ( false === $handle ) {
		return new WP_Error( 'jluxe_ai_transport', 'ارتباط با سرور سرویس هوش مصنوعی برقرار نشد.', array( 'status' => 502 ) );
	}

	$status       = 0;
	$response_body = '';
	$last_heartbeat = time();
	curl_setopt_array(
		$handle,
		array(
			CURLOPT_POST           => true,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_CONNECTTIMEOUT => 15,
			CURLOPT_TIMEOUT        => 90,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_HEADERFUNCTION => static function ( $curl, string $line ) use ( &$status ): int {
				if ( preg_match( '/^HTTP\/\S+\s+(\d{3})/i', trim( $line ), $matches ) ) {
					$status = (int) $matches[1];
				}
				return strlen( $line );
			},
			CURLOPT_WRITEFUNCTION  => static function ( $curl, string $chunk ) use ( &$status, &$response_body, &$parser_state, $on_event ): int {
				$response_body .= $chunk;
				if ( $status < 400 ) {
					jluxe_ai_sse_parse_chunk( $chunk, $parser_state, $on_event );
				}
				if ( function_exists( 'connection_aborted' ) && connection_aborted() ) {
					return 0;
				}
				return strlen( $chunk );
			},
		)
	);

	if ( defined( 'CURLOPT_NOPROGRESS' ) && defined( 'CURLOPT_XFERINFOFUNCTION' ) ) {
		curl_setopt( $handle, CURLOPT_NOPROGRESS, false );
		curl_setopt(
			$handle,
			CURLOPT_XFERINFOFUNCTION,
			static function ( $curl, $download_total, $downloaded, $upload_total, $uploaded ) use ( &$last_heartbeat ): int {
				if ( function_exists( 'connection_aborted' ) && connection_aborted() ) {
					return 1;
				}
				if ( time() - $last_heartbeat >= 15 ) {
					echo ": keep-alive\n\n";
					flush();
					$last_heartbeat = time();
				}
				return 0;
			}
		);
	}

	$ok     = curl_exec( $handle );
	$error  = false === $ok ? (string) curl_error( $handle ) : '';
	$status = (int) curl_getinfo( $handle, CURLINFO_RESPONSE_CODE );
	curl_close( $handle );
	if ( $status < 400 && '' === $error ) {
		jluxe_ai_sse_parse_chunk( '', $parser_state, $on_event, true );
	}
	return array( 'status' => $status, 'error' => $error, 'body' => $response_body );
}

function jluxe_ai_openai_stream_error( array $settings, int $status, string $body ) {
	$parsed = json_decode( $body, true );
	$detail = is_array( $parsed ) ? (string) ( $parsed['error']['message'] ?? '' ) : '';
	jluxe_ai_log_error( $settings, 'openai stream error (' . $status . '): ' . $body );
	if ( in_array( $status, array( 401, 403 ), true ) ) {
		return new WP_Error( 'jluxe_ai_upstream', 'کلید API سرویس هوش مصنوعی پذیرفته نشد؛ مقدار آن را در پنل بررسی کنید.', array( 'status' => 502 ) );
	}
	if ( 429 === $status ) {
		return new WP_Error( 'jluxe_ai_upstream', 'سرویس هوش مصنوعی محدودیت نرخ/اعتبار دارد؛ کمی بعد دوباره تلاش کنید.', array( 'status' => 502 ) );
	}
	if ( '' !== $detail ) {
		$detail = wp_strip_all_tags( $detail );
		if ( jluxe_strlen( $detail ) > 200 ) {
			$detail = jluxe_substr( $detail, 0, 200 ) . '…';
		}
		return new WP_Error( 'jluxe_ai_upstream', 'سرویس هوش مصنوعی درخواست را رد کرد: ' . $detail, array( 'status' => 502 ) );
	}
	return new WP_Error( 'jluxe_ai_upstream', 'پاسخی از سرویس هوش مصنوعی دریافت نشد.', array( 'status' => 502 ) );
}

function jluxe_ai_openai_stream_round( array $settings, string $api_key, string $system, array $messages, array $tool_specs, bool &$response_started ) {
	$oa_messages = array( array( 'role' => 'system', 'content' => $system ) );
	foreach ( $messages as $message ) {
		if ( ! is_array( $message ) || ! in_array( $message['role'] ?? '', array( 'user', 'assistant', 'tool' ), true ) ) {
			continue;
		}
		$entry = array( 'role' => $message['role'] );
		if ( array_key_exists( 'content', $message ) ) {
			$entry['content'] = $message['content'];
		}
		// Internal tool-round messages must retain the OpenAI tool-call handshake.
		if ( 'assistant' === $entry['role'] && is_array( $message['tool_calls'] ?? null ) ) {
			$entry['tool_calls'] = $message['tool_calls'];
		}
		if ( 'tool' === $entry['role'] && isset( $message['tool_call_id'] ) ) {
			$entry['tool_call_id'] = (string) $message['tool_call_id'];
		}
		$oa_messages[] = $entry;
	}
	$model       = $settings['model'] ?: 'gpt-4o-mini';
	$oa_tools    = jluxe_ai_tools_to_openai_schema( $tool_specs );
	$is_reasoning = jluxe_ai_model_supports_reasoning_effort( $model );
	$body         = array( 'model' => $model, 'messages' => $oa_messages, 'stream' => true );
	if ( $is_reasoning ) {
		$body['max_completion_tokens'] = (int) $settings['max_tokens'];
		if ( ! empty( $settings['reasoning_effort'] ) ) {
			$body['reasoning_effort'] = $settings['reasoning_effort'];
		}
	} else {
		$body['temperature'] = (float) $settings['temperature'];
		if ( (int) $settings['max_tokens'] > 0 ) {
			$body['max_tokens'] = (int) $settings['max_tokens'];
		}
	}
	if ( ! empty( $oa_tools ) ) {
		$body['tools']       = $oa_tools;
		$body['tool_choice'] = 'auto';
	}

	$content       = '';
	$tool_calls    = array();
	$finish_reason = '';
	$provider_error = '';
	$on_event      = static function ( string $event, string $data ) use ( &$content, &$tool_calls, &$finish_reason, &$provider_error, &$response_started ): void {
		if ( '[DONE]' === trim( $data ) ) {
			return;
		}
		$parsed = json_decode( $data, true );
		if ( ! is_array( $parsed ) ) {
			return;
		}
		$response_started = true;
		if ( isset( $parsed['error'] ) ) {
			$provider_error = (string) ( $parsed['error']['message'] ?? 'پاسخی از سرویس هوش مصنوعی دریافت نشد.' );
			return;
		}
		$choice = $parsed['choices'][0] ?? array();
		$delta  = is_array( $choice ) && is_array( $choice['delta'] ?? null ) ? $choice['delta'] : array();
		$text   = $delta['content'] ?? '';
		if ( is_string( $text ) && '' !== $text ) {
			$content .= $text;
			jluxe_ai_sse_send_event( 'delta', array( 'text' => $text ) );
		}
		if ( is_array( $text ) ) {
			foreach ( $text as $part ) {
				if ( is_array( $part ) && 'text' === ( $part['type'] ?? '' ) && is_string( $part['text'] ?? null ) ) {
					$content .= $part['text'];
					jluxe_ai_sse_send_event( 'delta', array( 'text' => $part['text'] ) );
				}
			}
		}
		foreach ( (array) ( $delta['tool_calls'] ?? array() ) as $call_delta ) {
			if ( ! is_array( $call_delta ) ) {
				continue;
			}
			$index = isset( $call_delta['index'] ) ? max( 0, (int) $call_delta['index'] ) : count( $tool_calls );
			if ( ! isset( $tool_calls[ $index ] ) ) {
				$tool_calls[ $index ] = array( 'id' => '', 'type' => 'function', 'function' => array( 'name' => '', 'arguments' => '' ) );
			}
			if ( ! empty( $call_delta['id'] ) ) {
				$tool_calls[ $index ]['id'] = (string) $call_delta['id'];
			}
			if ( ! empty( $call_delta['type'] ) ) {
				$tool_calls[ $index ]['type'] = (string) $call_delta['type'];
			}
			$function_delta = is_array( $call_delta['function'] ?? null ) ? $call_delta['function'] : array();
			if ( isset( $function_delta['name'] ) ) {
				$tool_calls[ $index ]['function']['name'] .= (string) $function_delta['name'];
			}
			if ( isset( $function_delta['arguments'] ) ) {
				$tool_calls[ $index ]['function']['arguments'] .= (string) $function_delta['arguments'];
			}
		}
		if ( ! empty( $choice['finish_reason'] ) ) {
			$finish_reason = (string) $choice['finish_reason'];
		}
	};

	$request_body = wp_json_encode( $body );
	if ( ! is_string( $request_body ) ) {
		return new WP_Error( 'jluxe_ai_upstream', 'درخواستِ دستیار آماده نشد.', array( 'status' => 502 ) );
	}
	$url = untrailingslashit( ! empty( $settings['base_url'] ) ? $settings['base_url'] : 'https://api.openai.com/v1' ) . '/chat/completions';
	$response = jluxe_ai_stream_http_post(
		$url,
		array(
			'Authorization: Bearer ' . $api_key,
			'Content-Type: application/json',
			'Accept: text/event-stream',
			'Cache-Control: no-cache',
		),
		$request_body,
		$on_event
	);
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	if ( '' !== $response['error'] ) {
		$detail = wp_strip_all_tags( $response['error'] );
		if ( jluxe_strlen( $detail ) > 200 ) {
			$detail = jluxe_substr( $detail, 0, 200 ) . '…';
		}
		jluxe_ai_log_error( $settings, 'openai stream transport error: ' . $detail );
		return new WP_Error( 'jluxe_ai_transport', 'ارتباط با سرور سرویس هوش مصنوعی برقرار نشد (' . $detail . ').', array( 'status' => 502 ) );
	}
	if ( $response['status'] < 200 || $response['status'] >= 300 ) {
		return jluxe_ai_openai_stream_error( $settings, (int) $response['status'], (string) $response['body'] );
	}
	if ( '' !== $provider_error ) {
		jluxe_ai_log_error( $settings, 'openai stream provider error: ' . $provider_error );
		return new WP_Error( 'jluxe_ai_upstream', 'سرویس هوش مصنوعی درخواست را رد کرد: ' . wp_strip_all_tags( $provider_error ), array( 'status' => 502 ) );
	}

	// بعضی serverهای سازگار، stream=true را نادیده می‌گیرند و یک JSON کامل می‌دهند.
	// در این حالت همان پاسخ را یک‌باره به‌صورت یک delta می‌فرستیم؛ قابلیت چت حفظ می‌شود.
	if ( '' === $content && empty( $tool_calls ) && '' !== $response['body'] ) {
		$full = json_decode( $response['body'], true );
		$message = $full['choices'][0]['message'] ?? array();
		if ( is_array( $message ) && is_string( $message['content'] ?? null ) ) {
			$content = $message['content'];
			if ( '' !== $content ) {
				$response_started = true;
				jluxe_ai_sse_send_event( 'delta', array( 'text' => $content ) );
			}
		}
		if ( is_array( $message ) && ! empty( $message['tool_calls'] ) ) {
			$tool_calls = (array) $message['tool_calls'];
		}
	}

	ksort( $tool_calls );
	return array(
		'content'       => $content,
		'tool_calls'    => array_values( $tool_calls ),
		'finish_reason' => $finish_reason,
	);
}

function jluxe_call_openai_compatible_stream( array $settings, string $api_key, string $system, array $messages, array $tool_specs ) {
	$hosts = array();
	if ( 'gapgpt' === $settings['provider'] ) {
		$primary   = 'https://api.gapgpt.app/v1';
		$alternate = 'https://api.gapapi.com/v1';
		$cached    = get_transient( 'jluxe_gapgpt_base' );
		if ( in_array( $cached, array( $primary, $alternate ), true ) ) {
			$hosts[] = $cached;
		}
		foreach ( array( $primary, $alternate ) as $host ) {
			if ( ! in_array( $host, $hosts, true ) ) {
				$hosts[] = $host;
			}
		}
	} else {
		$hosts[] = ! empty( $settings['base_url'] ) ? untrailingslashit( $settings['base_url'] ) : 'https://api.openai.com/v1';
	}

	$oa_messages    = array( array( 'role' => 'system', 'content' => $system ) );
	$reply          = '';
	$response_started = false;
	foreach ( $messages as $message ) {
		$oa_messages[] = array( 'role' => $message['role'], 'content' => $message['content'] );
	}

	for ( $round = 0; $round < 3; $round++ ) {
		$round_result = null;
		$last_transport = null;
		foreach ( $hosts as $host_index => $host ) {
			$round_settings             = $settings;
			$round_settings['base_url'] = $host;
			$attempt = jluxe_ai_openai_stream_round( $round_settings, $api_key, $system, array_slice( $oa_messages, 1 ), $tool_specs, $response_started );
			if ( is_wp_error( $attempt ) ) {
				if ( 'gapgpt' === $settings['provider'] && 'jluxe_ai_transport' === $attempt->get_error_code() && ! $response_started && isset( $hosts[ $host_index + 1 ] ) ) {
					$last_transport = $attempt;
					continue;
				}
				if ( 'gapgpt' === $settings['provider'] && 'jluxe_ai_transport' === $attempt->get_error_code() && ! $response_started ) {
					$last_transport = $attempt;
					break;
				}
				return $attempt;
			}
			if ( 'gapgpt' === $settings['provider'] ) {
				set_transient( 'jluxe_gapgpt_base', $host, 12 * HOUR_IN_SECONDS );
			}
			$round_result = $attempt;
			break;
		}

		if ( null === $round_result ) {
			if ( 'gapgpt' === $settings['provider'] && $last_transport ) {
				jluxe_ai_log_error( $settings, 'gapgpt stream transport failed on both hosts: ' . $last_transport->get_error_message() );
				return new WP_Error(
					'jluxe_ai_transport',
					'اتصال به سرور GapGPT برقرار نشد (هر دو آدرس رسمی api.gapgpt.app و api.gapapi.com امتحان شد). ' . $last_transport->get_error_message(),
					array( 'status' => 502 )
				);
			}
			return new WP_Error( 'jluxe_ai_transport', 'ارتباط با سرویس هوش مصنوعی برقرار نشد.', array( 'status' => 502 ) );
		}

		$reply .= $round_result['content'];
		$tool_calls = $round_result['tool_calls'];
		if ( empty( $tool_calls ) ) {
			return $reply;
		}

		$oa_messages[] = array(
			'role'       => 'assistant',
			'content'    => '' !== $round_result['content'] ? $round_result['content'] : null,
			'tool_calls' => $tool_calls,
		);
		foreach ( $tool_calls as $call ) {
			$name   = (string) ( $call['function']['name'] ?? '' );
			$args   = json_decode( (string) ( $call['function']['arguments'] ?? '{}' ), true );
			$args   = is_array( $args ) ? $args : array();
			$result = jluxe_ai_execute_tool( $name, $args, $settings['tools'] );
			$oa_messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => (string) ( $call['id'] ?? '' ),
				'content'      => wp_json_encode( $result ),
			);
		}
	}

	return new WP_Error( 'jluxe_ai_tool_loop', 'دستیار نتونست به پاسخ نهایی برسه.', array( 'status' => 502 ) );
}

function jluxe_ai_stream_response( array $context ): void {
	jluxe_ai_sse_begin();
	jluxe_ai_sse_send_event( 'start', array( 'streaming' => true ) );
	try {
		$reply = jluxe_call_openai_compatible_stream(
			$context['settings'],
			$context['api_key'],
			$context['system'],
			$context['messages'],
			$context['tool_specs']
		);
		$payload = jluxe_ai_response_payload( $reply );
		if ( is_wp_error( $payload ) ) {
			jluxe_ai_sse_send_event( 'error', array( 'code' => $payload->get_error_code(), 'message' => $payload->get_error_message() ) );
			return;
		}
		jluxe_ai_sse_send_event( 'done', array( 'reply' => $payload['reply'], 'products' => $payload['products'] ) );
	} catch ( Throwable $error ) {
		jluxe_ai_log_error( $context['settings'], 'stream handler exception: ' . $error->getMessage() );
		jluxe_ai_sse_send_event( 'error', array( 'code' => 'jluxe_ai_stream_error', 'message' => 'در پاسخ‌گویی خطایی پیش آمد؛ دوباره تلاش کن.' ) );
	}
}
