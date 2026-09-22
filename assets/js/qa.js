/**
 * ارسال فرم «پرسش و پاسخ» صفحه‌ی محصول از طریق AJAX وردپرس.
 * فقط در صفحه‌ی محصول لود می‌شه (به inc/qa.php نگاه کن).
 */
(function () {
	"use strict";

	document.addEventListener("submit", function (event) {
		var form = event.target.closest("[data-jluxe-qa-form]");
		if (!form) {
			return;
		}
		event.preventDefault();

		var messageEl = form.querySelector("[data-jluxe-qa-message]");
		var submitBtn = form.querySelector('button[type="submit"]');
		var formData = new FormData(form);
		formData.append("action", "jluxe_qa_submit");

		submitBtn.disabled = true;

		fetch(window.jluxeQa.ajaxUrl, {
			method: "POST",
			body: formData,
			credentials: "same-origin",
		})
			.then(function (res) {
				return res.json();
			})
			.then(function (data) {
				var message = data && data.data && data.data.message ? data.data.message : "";
				messageEl.textContent = message;
				messageEl.classList.remove("hidden");
				messageEl.style.color = data.success ? "hsl(var(--success))" : "hsl(var(--error))";
				if (data.success) {
					form.reset();
				}
			})
			.catch(function () {
				messageEl.textContent = "خطا در ارتباط با سرور. لطفاً دوباره تلاش کنید.";
				messageEl.classList.remove("hidden");
				messageEl.style.color = "hsl(var(--error))";
			})
			.finally(function () {
				submitBtn.disabled = false;
			});
	});
})();
