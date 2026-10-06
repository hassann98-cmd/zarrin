<?php
// Development checks must never execute through a public web request.
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
// TOKEN_PARSE invokes the running PHP version's parser without executing WordPress templates.
$root=dirname(__DIR__,2);
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
$count=0;
foreach($files as $file){
	$relative=substr($file->getPathname(),strlen($root)+1);
	if(preg_match('~^(node_modules|\.git|\.cache|dist|assets/compiled)/~',$relative)||$file->getExtension()!=='php')continue;
	token_get_all(file_get_contents($file->getPathname()),TOKEN_PARSE);
	$count++;
}
echo "PHP_LINT_OK: $count files on PHP ".PHP_VERSION."\n";
