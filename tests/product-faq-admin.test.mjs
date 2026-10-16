import test from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import jquery from "jquery";
import { JSDOM } from "jsdom";

const source = fs.readFileSync(new URL("../assets/js/product-faq-admin.js", import.meta.url), "utf8");

function editorMarkup() {
  return `<!doctype html><html lang="fa" dir="rtl"><body>
    <textarea id="jluxe-faq-bulk-paste"></textarea>
    <button id="jluxe-faq-import-replace" disabled>جایگزینی</button>
    <button id="jluxe-faq-import-append" disabled>افزودن</button>
    <p id="jluxe-faq-bulk-status" role="status" aria-live="polite"></p>
    <div id="jluxe-faq-items">
      <div class="jluxe-faq-item">
        <strong class="jluxe-faq-item-title">سوال ۱</strong>
        <input type="text" name="jluxe_product_faq[0][question]" value="سوال فعلی؟">
        <textarea name="jluxe_product_faq[0][answer]">پاسخ فعلی</textarea>
        <button type="button" class="jluxe-faq-remove-item">حذف</button>
      </div>
      <div class="jluxe-faq-item">
        <strong class="jluxe-faq-item-title">سوال ۲</strong>
        <input type="text" name="jluxe_product_faq[1][question]" value="سوال دوم فعلی؟">
        <textarea name="jluxe_product_faq[1][answer]">پاسخ دوم فعلی</textarea>
        <button type="button" class="jluxe-faq-remove-item">حذف</button>
      </div>
    </div>
    <template id="jluxe-faq-item-template">
      <div class="jluxe-faq-item">
        <strong class="jluxe-faq-item-title">سوال</strong>
        <input type="text" name="jluxe_product_faq[__INDEX__][question]" value="">
        <textarea name="jluxe_product_faq[__INDEX__][answer]"></textarea>
        <button type="button" class="jluxe-faq-remove-item">حذف</button>
      </div>
    </template>
    <button type="button" id="jluxe-add-faq-item">افزودن سوال جدید</button>
  </body></html>`;
}

function bootEditor(t, confirmAnswer = true) {
  const dom = new JSDOM(editorMarkup(), {
    url: "https://shop.test/wp-admin/post.php",
    runScripts: "outside-only",
    pretendToBeVisual: true,
  });
  t.after(() => dom.window.close());
  const { window } = dom;
  const confirmations = [];
  window.confirm = (message) => {
    confirmations.push(message);
    return typeof confirmAnswer === "function" ? confirmAnswer(message) : confirmAnswer;
  };
  window.jQuery = jquery(window);
  window.eval(source);
  if (window.document.readyState === "loading") {
    window.document.dispatchEvent(new window.Event("DOMContentLoaded", { bubbles: true }));
  }
  return { dom, window, confirmations };
}

function pasteClipboard(window, input, data) {
  const event = new window.Event("paste", { bubbles: true, cancelable: true });
  Object.defineProperty(event, "clipboardData", {
    value: { getData: (type) => data[type] || "" },
  });
  input.dispatchEvent(event);
  return event;
}

function faqRows(document) {
  return Array.from(document.querySelectorAll("#jluxe-faq-items .jluxe-faq-item")).map((row) => ({
    question: row.querySelector('input[name$="[question]"]').value,
    answer: row.querySelector('textarea[name$="[answer]"]').value,
  }));
}

const buyersFaqText = [
  "سوالات متداول خریداران (مناسب برای بلاک FAQ رنک مث)",
  "",
  "**۱. کفه ام‌دی‌اف ۳ میلی‌متری در این سبد چه کاربردی دارد؟** کفه ام‌دی‌اف قهوه‌ای رنگ علاوه بر زیبایی ظاهری، رطوبت اضافی سیب‌زمینی و پیاز را جذب کرده تا مانع از پوسیدگی آن‌ها شود و همچنین جلوی ریختن پوست پیاز و خاک را از لا‌به‌لای توری‌های فلزی به کف آشپزخانه می‌گیرد.",
  "",
  "**۲. آیا سبدهای این مدل ثابت هستند یا از بدنه جدا می‌شوند؟** تمامی ۳ سبد این محصول قابلیت جدا شدن از اسکلت اصلی را دارند تا بتوانید به راحتی آن‌ها را پر و خالی کنید یا کفه آن‌ها را دستمال بکشید.",
  "",
  "**۳. آیا بدنه فلزی محصول در محیط مرطوب آشپزخانه زنگ می‌زند؟** خیر؛ اسکلت و سبدهای این محصول از فلز مقاوم آبکاری شده با پوشش رنگ کوره‌ای درجه یک تولید شده‌اند که مقاومت بسیار بالایی در برابر رطوبت و زنگ‌زدگی دارد.",
  "",
  "**۴. این سبد ۳ طبقه چقدر فضا در آشپزخانه اشغال می‌کند؟** به دلیل طراحی بیضی‌شکل و هوشمندانه، این محصول تنها ۲۰ سانتی‌متر عرض و ۳۵ سانتی‌متر طول دارد و با ارتفاع ۶۰ سانتی‌متر، از فضای عمودی استفاده کرده و کمترین جای ممکن را روی زمین اشغال می‌کند.",
].join("\n");

test("R324 one paste recognizes the supplied Persian Markdown FAQ list and replaces the current rows safely", (t) => {
  const { window, confirmations } = bootEditor(t);
  const textarea = window.document.getElementById("jluxe-faq-bulk-paste");
  const event = pasteClipboard(window, textarea, { "text/plain": buyersFaqText });

  assert.equal(event.defaultPrevented, true, "recognized bulk FAQ paste is staged in the dedicated field");
  assert.equal(window.document.getElementById("jluxe-faq-import-replace").disabled, false);
  assert.equal(window.document.getElementById("jluxe-faq-import-append").disabled, false);
  assert.match(window.document.getElementById("jluxe-faq-bulk-status").textContent, /۴ جفت سوال و جواب/);
  assert.match(textarea.value, /سوالات متداول خریداران/);

  window.document.getElementById("jluxe-faq-import-replace").click();
  const rows = faqRows(window.document);
  assert.equal(confirmations.length, 1, "replacing populated FAQs requires explicit confirmation");
  assert.equal(rows.length, 4, "the heading is ignored and the four question/answer pairs become four editor rows");
  assert.equal(rows[0].question, "کفه ام‌دی‌اف ۳ میلی‌متری در این سبد چه کاربردی دارد؟");
  assert.equal(rows[0].answer, "کفه ام‌دی‌اف قهوه‌ای رنگ علاوه بر زیبایی ظاهری، رطوبت اضافی سیب‌زمینی و پیاز را جذب کرده تا مانع از پوسیدگی آن‌ها شود و همچنین جلوی ریختن پوست پیاز و خاک را از لا‌به‌لای توری‌های فلزی به کف آشپزخانه می‌گیرد.");
  assert.equal(rows[1].question, "آیا سبدهای این مدل ثابت هستند یا از بدنه جدا می‌شوند؟");
  assert.match(rows[1].answer, /^تمامی ۳ سبد/);
  assert.equal(rows[2].question, "آیا بدنه فلزی محصول در محیط مرطوب آشپزخانه زنگ می‌زند؟");
  assert.match(rows[2].answer, /^خیر؛ اسکلت/);
  assert.equal(rows[3].question, "این سبد ۳ طبقه چقدر فضا در آشپزخانه اشغال می‌کند؟");
  assert.match(rows[3].answer, /^به دلیل طراحی بیضی‌شکل/);
  assert.doesNotMatch(rows[0].question, /\*\*|^۱\./, "Markdown bold and list numbering are not stored as part of the question");
  assert.match(window.document.getElementById("jluxe-faq-bulk-status").textContent, /با ذخیرهٔ محصول ثبت می‌شود/);
});

test("R324 copied Word/HTML questions parse from bold elements and only safe text enters fields", (t) => {
  const { window } = bootEditor(t);
  const textarea = window.document.getElementById("jluxe-faq-bulk-paste");
  const event = pasteClipboard(window, textarea, {
    "text/html": `<h2>سوالات متداول خریداران</h2>
      <p><strong>۱. آیا بدنه مقاوم است؟</strong> بله، بدنه فلزی با پوشش مقاوم ساخته شده است.</p>
      <p><b>۲. آیا سبد جدا می‌شود؟</b> <a href="https://attacker.test/" onclick="window.pastedCodeRan=true">بله، سبد قابل جداسازی است.</a><script>window.pastedCodeRan=true</script></p>`,
    "text/plain": "سوالات متداول خریداران\n۱. آیا بدنه مقاوم است؟ بله، بدنه فلزی با پوشش مقاوم ساخته شده است.\n۲. آیا سبد جدا می‌شود؟ بله، سبد قابل جداسازی است.",
  });

  assert.equal(event.defaultPrevented, true);
  window.document.getElementById("jluxe-faq-import-replace").click();
  const rows = faqRows(window.document);
  assert.equal(rows.length, 2);
  assert.deepEqual(rows[0], { question: "آیا بدنه مقاوم است؟", answer: "بله، بدنه فلزی با پوشش مقاوم ساخته شده است." });
  assert.deepEqual(rows[1], { question: "آیا سبد جدا می‌شود؟", answer: "بله، سبد قابل جداسازی است." });
  assert.equal(window.pastedCodeRan, undefined, "clipboard scripts and event-handler attributes never execute");
  assert.doesNotMatch(rows[1].answer, /<|attacker|onclick/);
});

test("R324 append preserves existing FAQs, while canceling replacement leaves every current row unchanged", (t) => {
  const { window, confirmations } = bootEditor(t, false);
  const textarea = window.document.getElementById("jluxe-faq-bulk-paste");
  pasteClipboard(window, textarea, {
    "text/plain": "۱. آیا ارسال رایگان است؟ بله، طبق شرایط فروشگاه.\n\n۲. چه زمانی ارسال می‌شود؟ زمان ارسال در صفحه محصول درج شده است.",
  });

  window.document.getElementById("jluxe-faq-import-replace").click();
  assert.equal(faqRows(window.document).length, 2, "declining replacement keeps existing entries intact");
  assert.equal(faqRows(window.document)[0].question, "سوال فعلی؟");
  assert.match(window.document.getElementById("jluxe-faq-bulk-status").textContent, /دست‌نخورده ماندند/);

  window.document.getElementById("jluxe-faq-import-append").click();
  const rows = faqRows(window.document);
  assert.equal(confirmations.length, 1);
  assert.equal(rows.length, 4, "append adds the two parsed pairs after the existing rows");
  assert.equal(rows[0].question, "سوال فعلی؟");
  assert.equal(rows[1].question, "سوال دوم فعلی؟");
  assert.equal(rows[2].question, "آیا ارسال رایگان است؟");
  assert.equal(rows[2].answer, "بله، طبق شرایط فروشگاه.");
  assert.equal(rows[3].question, "چه زمانی ارسال می‌شود؟");
});

test("R324 a plain unnumbered question-answer pair can be typed and imported without HTML", (t) => {
  const { window } = bootEditor(t);
  const textarea = window.document.getElementById("jluxe-faq-bulk-paste");
  textarea.value = "آیا این محصول قابل شست‌وشو است؟\nبله، با دستمال مرطوب تمیز می‌شود.";
  textarea.dispatchEvent(new window.Event("input", { bubbles: true }));

  assert.equal(window.document.getElementById("jluxe-faq-import-replace").disabled, false);
  window.document.getElementById("jluxe-faq-import-replace").click();
  assert.deepEqual(faqRows(window.document), [
    { question: "آیا این محصول قابل شست‌وشو است؟", answer: "بله، با دستمال مرطوب تمیز می‌شود." },
  ]);
});

test("R324 ordinary paste is left native, and pasting a new FAQ set into its own field never submits it as product data", (t) => {
  const { window } = bootEditor(t);
  const textarea = window.document.getElementById("jluxe-faq-bulk-paste");
  const event = pasteClipboard(window, textarea, { "text/plain": "این متن یک توضیح معمولی است و سوال ندارد." });
  assert.equal(event.defaultPrevented, false);
  assert.equal(window.document.getElementById("jluxe-faq-import-replace").disabled, true);
  assert.equal(window.document.querySelector('#jluxe-faq-bulk-paste[name]'), null, "the staging textarea is never posted or saved as FAQ content");
});
