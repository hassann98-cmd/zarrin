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

    function getGroupRows(group) {
      var rowsContainer = group ? group.querySelector("[data-jluxe-spec-rows]") : null;
      return rowsContainer ? Array.prototype.slice.call(rowsContainer.querySelectorAll(".jluxe-spec-row")) : [];
    }

    function updateMoveButtons(group) {
      var rows = getGroupRows(group);
      rows.forEach(function (row, index) {
        var moveUpButton = row.querySelector('[data-jluxe-spec-move-row="up"]');
        var moveDownButton = row.querySelector('[data-jluxe-spec-move-row="down"]');
        if (moveUpButton) {
          moveUpButton.disabled = index === 0;
        }
        if (moveDownButton) {
          moveDownButton.disabled = index === rows.length - 1;
        }
      });
    }

    function renumberRows(group) {
      var groupIndex = group.getAttribute("data-group-index") || "0";
      var rows = getGroupRows(group);
      rows.forEach(function (row, index) {
        var fields = getRowInputs(row);
        if (fields.label) {
          fields.label.name = "jluxe_product_specs[" + groupIndex + "][items][" + index + "][label]";
        }
        if (fields.value) {
          fields.value.name = "jluxe_product_specs[" + groupIndex + "][items][" + index + "][value]";
        }
      });
      group.setAttribute("data-next-row-index", String(rows.length));
    }

    function announceRowMove(group, row, index) {
      var status = group.querySelector("[data-jluxe-spec-order-status]");
      if (!status) {
        status = document.createElement("p");
        status.className = "description";
        status.setAttribute("data-jluxe-spec-order-status", "");
        status.setAttribute("role", "status");
        status.setAttribute("aria-live", "polite");
        var rowsContainer = group.querySelector("[data-jluxe-spec-rows]");
        if (rowsContainer && rowsContainer.parentNode) {
          rowsContainer.parentNode.insertBefore(status, rowsContainer.nextSibling);
        } else {
          group.appendChild(status);
        }
      }
      var fields = getRowInputs(row);
      var label = fields.label && fields.label.value.trim() ? "ویژگی «" + fields.label.value.trim() + "»" : "ویژگی";
      status.textContent = label + " به ردیف " + toPersianDigits(index + 1) + " منتقل شد.";
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
      updateMoveButtons(group);
      return row;
    }

    function cleanCellText(value) {
      return String(value == null ? "" : value)
        .replace(/\u00a0/g, " ")
        .replace(/[\r\n\t]+/g, " ")
        .replace(/\s{2,}/g, " ")
        .replace(/^\*\*(.*)\*\*$/, "$1")
        .replace(/^__(.*)__$/, "$1")
        .replace(/^\*(.*)\*$/, "$1")
        .replace(/^_(.*)_$/, "$1")
        .replace(/^`(.*)`$/, "$1")
        .trim();
    }

    function normalizeHeader(value) {
      return cleanCellText(value)
        .toLowerCase()
        .replace(/[\s\u200c\u200f*_`:\u061b-]/g, "");
    }

    function isHeaderRow(label, value) {
      var labelText = normalizeHeader(label);
      var valueText = normalizeHeader(value);
      var labelHeaders = ["نام", "عنوان", "نامویژگی", "ناممشخصه", "ویژگی", "مشخصه", "پارامتر", "attribute", "attributes", "feature", "features", "field", "key", "label", "name", "parameter", "parameters", "property", "properties", "spec", "specification", "specifications"];
      var valueHeaders = ["مقدار", "توضیحات", "شرح", "مشخصات", "اطلاعات", "جواب", "مقدارویژگی", "مقدارمشخصه", "value", "values", "description", "details", "information", "info", "answer", "result"];
      return labelHeaders.indexOf(labelText) !== -1 && valueHeaders.indexOf(valueText) !== -1;
    }

    function isMarkdownSeparator(cells) {
      return cells.length >= 2 && cells.every(function (cell) {
        return /^:?-{3,}:?$/.test(cleanCellText(cell).replace(/\s/g, ""));
      });
    }

    function appendParsedRow(output, cells) {
      if (!cells || cells.length < 2 || isMarkdownSeparator(cells)) {
        return false;
      }
      var label = cleanCellText(cells[0]);
      var value = cleanCellText(cells.slice(1).join(" | "));
      if (!label || !value || isHeaderRow(label, value)) {
        return false;
      }
      output.push({ label: label, value: value });
      return true;
    }

    function findHtmlTableTitle(parsedDocument, table) {
      var captions = table.querySelectorAll("caption");
      if (captions.length) {
        var caption = cleanCellText(captions[0].textContent);
        if (caption) {
          return caption;
        }
      }

      var title = "";
      var headings = parsedDocument.querySelectorAll("h1,h2,h3,h4,h5,h6");
      Array.prototype.forEach.call(headings, function (heading) {
        if (heading.compareDocumentPosition(table) & 4) {
          title = cleanCellText(heading.textContent);
        }
      });
      if (title) {
        return title;
      }

      var previous = table.previousElementSibling;
      while (previous) {
        if (previous.tagName && previous.tagName.toUpperCase() === "P") {
          var paragraph = cleanCellText(previous.textContent);
          if (paragraph && paragraph.length <= 120) {
            return paragraph;
          }
        }
        previous = previous.previousElementSibling;
      }
      return "";
    }

    function parseHtmlTable(html) {
      var emptyResult = { rows: [], title: "" };
      if (!html || typeof window.DOMParser !== "function") {
        return emptyResult;
      }
      var safeHtml = String(html)
        .replace(/<!--([\s\S]*?)-->/g, "")
        .replace(/<(script|style)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, "")
        .replace(/<(script|style)\b[^>]*\/?\s*>/gi, "")
        // Only text is imported. Remove every attribute before parsing so copied
        // img/iframe/style URLs and event handlers cannot load or execute.
        .replace(/<([a-z][a-z0-9:-]*)\b[^>]*>/gi, "<$1>");
      var parsedDocument;
      try {
        parsedDocument = new window.DOMParser().parseFromString(safeHtml, "text/html");
      } catch (error) {
        return emptyResult;
      }
      var tables = parsedDocument.querySelectorAll("table");
      var output = [];
      var title = tables.length ? findHtmlTableTitle(parsedDocument, tables[0]) : "";
      var hasDataRows = false;
      Array.prototype.forEach.call(tables, function (table) {
        Array.prototype.forEach.call(table.querySelectorAll("tr"), function (row) {
          if (typeof row.closest === "function" && row.closest("table") !== table) {
            return;
          }
          var cells = [];
          Array.prototype.forEach.call(row.children, function (cell) {
            var tagName = cell.tagName ? cell.tagName.toUpperCase() : "";
            if (tagName === "TH" || tagName === "TD") {
              cells.push(cleanCellText(cell.textContent));
            }
          });
          var nonEmptyCells = cells.filter(function (cell) { return !!cell; });
          var isHeader = row.parentElement && row.parentElement.tagName === "THEAD";
          if (isHeader) {
            if (!title && !hasDataRows && nonEmptyCells.length === 1) {
              title = nonEmptyCells[0];
            }
            return;
          }
          if (!title && !hasDataRows && nonEmptyCells.length === 1) {
            title = nonEmptyCells[0];
            return;
          }
          if (appendParsedRow(output, cells)) {
            hasDataRows = true;
          }
        });
      });
      return { rows: output, title: title };
    }

    function splitPlainTableLine(line) {
      if (line.indexOf("\t") !== -1) {
        return line.split("\t");
      }
      if (line.indexOf("|") !== -1) {
        return line.trim().replace(/^\|/, "").replace(/\|$/, "").split("|");
      }
      if (/\s{2,}/.test(line)) {
        return line.trim().split(/\s{2,}/);
      }
      return [];
    }

    function parsePlainTable(text) {
      var lines = String(text || "").replace(/\r\n?/g, "\n").split("\n");
      var output = [];
      var title = "";
      var spaceSeparatedRows = 0;
      var hasExplicitDelimiters = lines.some(function (line) {
        return line.indexOf("\t") !== -1 || line.indexOf("|") !== -1;
      });
      lines.forEach(function (line) {
        if (!line.trim()) {
          return;
        }
        if (line.indexOf("\t") === -1 && line.indexOf("|") === -1 && /\s{2,}/.test(line)) {
          spaceSeparatedRows += 1;
        }
        if (!title && !output.length && hasExplicitDelimiters && line.indexOf("\t") === -1 && line.indexOf("|") === -1) {
          title = cleanCellText(line);
          return;
        }
        var cells = splitPlainTableLine(line);
        var nonEmptyCells = cells.map(cleanCellText).filter(function (cell) { return !!cell; });
        if (!title && !output.length && nonEmptyCells.length === 1 && cells.length > 1) {
          title = nonEmptyCells[0];
          return;
        }
        appendParsedRow(output, cells);
      });
      // Two-column text aligned only with spaces is ambiguous; require multiple
      // structured lines before intercepting it, so ordinary sentences paste normally.
      if (!hasExplicitDelimiters && spaceSeparatedRows < 2) {
        return { rows: [], title: "" };
      }
      return { rows: output, title: title };
    }

    function clipboardValue(clipboard, type) {
      try {
        return clipboard && typeof clipboard.getData === "function" ? clipboard.getData(type) : "";
      } catch (error) {
        return "";
      }
    }

    function getClipboardContent(clipboard) {
      var html = clipboardValue(clipboard, "text/html");
      var htmlContent = parseHtmlTable(html);
      var text = clipboardValue(clipboard, "text/plain") || clipboardValue(clipboard, "Text");
      var plainContent = parsePlainTable(text);
      if (htmlContent.rows.length) {
        return { rows: htmlContent.rows, title: htmlContent.title || plainContent.title };
      }
      return { rows: plainContent.rows, title: htmlContent.title || plainContent.title };
    }

    function getRowInputs(row) {
      return {
        label: row ? row.querySelector('input[name$="[label]"]') : null,
        value: row ? row.querySelector('input[name$="[value]"]') : null,
      };
    }

    function isEmptyRow(row) {
      var fields = getRowInputs(row);
      return !!fields.label && !!fields.value && !fields.label.value.trim() && !fields.value.value.trim();
    }

    function setInputValue(input, value) {
      input.value = value;
      ["input", "change"].forEach(function (eventName) {
        var event;
        try {
          event = new window.Event(eventName, { bubbles: true });
        } catch (error) {
          event = document.createEvent("Event");
          event.initEvent(eventName, true, false);
        }
        input.dispatchEvent(event);
      });
    }

    function toPersianDigits(value) {
      return String(value).replace(/[0-9]/g, function (digit) {
        return "۰۱۲۳۴۵۶۷۸۹".charAt(Number(digit));
      });
    }

    function announcePaste(group, message) {
      var status = group.querySelector("[data-jluxe-spec-paste-status]");
      if (!status) {
        status = document.createElement("p");
        status.className = "description";
        status.setAttribute("data-jluxe-spec-paste-status", "");
        status.setAttribute("role", "status");
        status.setAttribute("aria-live", "polite");
        var rows = group.querySelector("[data-jluxe-spec-rows]");
        if (rows && rows.parentNode) {
          rows.parentNode.insertBefore(status, rows.nextSibling);
        } else {
          group.appendChild(status);
        }
      }
      status.textContent = message;
    }

    function importRows(group, sourceRows, preferredRow, importedGroupTitle) {
      var rowsContainer = group.querySelector("[data-jluxe-spec-rows]");
      if (!rowsContainer) {
        return;
      }
      var existingRows = Array.prototype.slice.call(rowsContainer.querySelectorAll(".jluxe-spec-row"));
      var blankRows = existingRows.filter(isEmptyRow);
      var capacity = existingRows.length <= 100 ? 100 - existingRows.length + blankRows.length : 0;
      var rowsToImport = sourceRows.slice(0, capacity);
      if (!rowsToImport.length) {
        announcePaste(group, "برای این گروه ظرفیتِ بیشتری نیست؛ حداکثر ۱۰۰ ردیف در هر گروه پذیرفته می‌شود.");
        return;
      }
      if (preferredRow && blankRows.indexOf(preferredRow) !== -1) {
        blankRows.splice(blankRows.indexOf(preferredRow), 1);
        blankRows.unshift(preferredRow);
      }
      rowsToImport.forEach(function (item, index) {
        var row = blankRows[index] || addRow(group);
        var fields = getRowInputs(row);
        if (fields.label && fields.value) {
          setInputValue(fields.label, item.label);
          setInputValue(fields.value, item.value);
        }
      });
      var titleField = group.querySelector('input[name$="[title]"]');
      if (importedGroupTitle && titleField) {
        setInputValue(titleField, importedGroupTitle);
      }
      var message = toPersianDigits(rowsToImport.length) + " ویژگی به گروه فعلی اضافه شد؛ ویژگی‌های قبلی حفظ شدند.";
      if (importedGroupTitle && titleField) {
        message = "عنوان گروه «" + importedGroupTitle + "» هم ثبت شد. " + message;
      }
      if (rowsToImport.length < sourceRows.length) {
        message += " " + toPersianDigits(sourceRows.length - rowsToImport.length) + " ردیف به‌دلیل سقف ۱۰۰ ویژگی وارد نشد.";
      }
      announcePaste(group, message);
    }

    Array.prototype.forEach.call(root.querySelectorAll("[data-jluxe-spec-group]"), updateMoveButtons);

    addGroupButton.addEventListener("click", function () {
      var groupIndex = nextGroupIndex;
      nextGroupIndex += 1;
      root.setAttribute("data-next-group-index", String(nextGroupIndex));
      var fragment = applyTokens(groupTemplate.content.cloneNode(true), { GROUP: groupIndex });
      var group = fragment.querySelector("[data-jluxe-spec-group]");
      root.appendChild(fragment);
      if (group) {
        addRow(group);
        var focusTarget = group.querySelector('input[name$="[title]"]');
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

      var moveRowButton = target.closest("[data-jluxe-spec-move-row]");
      if (moveRowButton) {
        var movingRow = moveRowButton.closest(".jluxe-spec-row");
        var movingGroup = movingRow ? movingRow.closest("[data-jluxe-spec-group]") : null;
        var rowsContainer = movingGroup ? movingGroup.querySelector("[data-jluxe-spec-rows]") : null;
        var groupRows = movingGroup ? getGroupRows(movingGroup) : [];
        var currentIndex = groupRows.indexOf(movingRow);
        var direction = moveRowButton.getAttribute("data-jluxe-spec-move-row");
        var destination = -1;

        if (direction === "up" && currentIndex > 0) {
          destination = currentIndex - 1;
          rowsContainer.insertBefore(movingRow, groupRows[destination]);
        } else if (direction === "down" && currentIndex !== -1 && currentIndex < groupRows.length - 1) {
          destination = currentIndex + 1;
          rowsContainer.insertBefore(groupRows[destination], movingRow);
        }

        if (destination !== -1) {
          renumberRows(movingGroup);
          updateMoveButtons(movingGroup);
          announceRowMove(movingGroup, movingRow, destination);

          var reverseDirection = direction === "up" ? "down" : "up";
          var focusButton = movingRow.querySelector('[data-jluxe-spec-move-row="' + reverseDirection + '"]');
          if (focusButton && !focusButton.disabled) {
            focusButton.focus();
          } else {
            var focusField = getRowInputs(movingRow).label;
            if (focusField) {
              focusField.focus();
            }
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
            var rowGroup = row.closest("[data-jluxe-spec-group]");
            row.remove();
            if (rowGroup) {
              renumberRows(rowGroup);
              updateMoveButtons(rowGroup);
            }
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

    root.addEventListener("paste", function (event) {
      var target = event.target;
      var targetName = target && target.name ? target.name : "";
      var isGroupTitle = /\[title\]$/.test(targetName);
      var isRowField = /\[(?:label|value)\]$/.test(targetName);
      if (!target || target.tagName !== "INPUT" || (!isGroupTitle && !isRowField)) {
        return;
      }
      var row = typeof target.closest === "function" ? target.closest(".jluxe-spec-row") : null;
      var group = typeof target.closest === "function" ? target.closest("[data-jluxe-spec-group]") : null;
      if (!group) {
        return;
      }
      var clipboard = event.clipboardData || window.clipboardData;
      var content = getClipboardContent(clipboard);
      if (!content.rows.length) {
        return;
      }
      event.preventDefault();
      importRows(group, content.rows, row, content.title);
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initProductSpecificationsEditor, { once: true });
  } else {
    initProductSpecificationsEditor();
  }
})();
