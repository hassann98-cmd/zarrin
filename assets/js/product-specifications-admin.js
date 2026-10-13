/* Product grouped-specifications repeater for inc/product-specifications.php. */
(function () {
  "use strict";

  function initProductSpecificationsEditor() {
    var root = document.getElementById("jluxe-product-spec-groups");
    var groupTemplate = document.getElementById("jluxe-product-spec-group-template");
    var rowTemplate = document.getElementById("jluxe-product-spec-row-template");
    var addGroupButton = document.querySelector("[data-jluxe-spec-add-group]");

    if (!root || !groupTemplate || !rowTemplate || !addGroupButton) {
      return;
    }

    var nextGroupIndex = parseInt(root.getAttribute("data-next-group-index") || "0", 10);
    if (!Number.isFinite(nextGroupIndex) || nextGroupIndex < 0) {
      nextGroupIndex = 0;
    }

    function applyTokens(fragment, tokens) {
      Array.prototype.forEach.call(fragment.querySelectorAll("*"), function (element) {
        Array.prototype.forEach.call(element.attributes, function (attribute) {
          var value = attribute.value;
          Object.keys(tokens).forEach(function (token) {
            value = value.replace(new RegExp("__" + token + "__", "g"), String(tokens[token]));
          });
          if (value !== attribute.value) {
            element.setAttribute(attribute.name, value);
          }
        });
      });
      return fragment;
    }

    function addRow(group) {
      var rows = group.querySelector("[data-jluxe-spec-rows]");
      if (!rows) {
        return null;
      }
      var groupIndex = group.getAttribute("data-group-index") || "0";
      var rowIndex = parseInt(group.getAttribute("data-next-row-index") || "0", 10);
      if (!Number.isFinite(rowIndex) || rowIndex < 0) {
        rowIndex = 0;
      }
      group.setAttribute("data-next-row-index", String(rowIndex + 1));
      var fragment = applyTokens(rowTemplate.content.cloneNode(true), {
        GROUP: groupIndex,
        ROW: rowIndex,
      });
      var row = fragment.querySelector(".jluxe-spec-row");
      rows.appendChild(fragment);
      return row;
    }

    addGroupButton.addEventListener("click", function () {
      var groupIndex = nextGroupIndex;
      nextGroupIndex += 1;
      root.setAttribute("data-next-group-index", String(nextGroupIndex));
      var fragment = applyTokens(groupTemplate.content.cloneNode(true), { GROUP: groupIndex });
      var group = fragment.querySelector("[data-jluxe-spec-group]");
      root.appendChild(fragment);
      if (group) {
        var row = addRow(group);
        var focusTarget = row ? row.querySelector('input[type="text"]') : group.querySelector('input[type="text"]');
        if (focusTarget) {
          focusTarget.focus();
        }
      }
    });

    root.addEventListener("click", function (event) {
      var target = event.target;
      if (!target || typeof target.closest !== "function") {
        return;
      }

      var addRowButton = target.closest("[data-jluxe-spec-add-row]");
      if (addRowButton) {
        var addRowGroup = addRowButton.closest("[data-jluxe-spec-group]");
        var newRow = addRowGroup ? addRow(addRowGroup) : null;
        if (newRow) {
          var rowInput = newRow.querySelector('input[type="text"]');
          if (rowInput) {
            rowInput.focus();
          }
        }
        return;
      }

      var removeRowButton = target.closest("[data-jluxe-spec-remove-row]");
      if (removeRowButton) {
        var row = removeRowButton.closest(".jluxe-spec-row");
        if (row) {
          var hasRowContent = Array.prototype.some.call(row.querySelectorAll('input[type="text"]'), function (input) {
            return input.value.trim() !== "";
          });
          if (!hasRowContent || window.confirm("این ویژگی حذف بشه؟")) {
            row.remove();
          }
        }
        return;
      }

      var removeGroupButton = target.closest("[data-jluxe-spec-remove-group]");
      if (removeGroupButton) {
        var group = removeGroupButton.closest("[data-jluxe-spec-group]");
        if (group) {
          var hasGroupContent = Array.prototype.some.call(group.querySelectorAll('input[type="text"]'), function (input) {
            return input.value.trim() !== "";
          });
          if (!hasGroupContent || window.confirm("این گروه و همهٔ ویژگی‌هایش حذف بشه؟")) {
            group.remove();
          }
        }
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initProductSpecificationsEditor, { once: true });
  } else {
    initProductSpecificationsEditor();
  }
})();
