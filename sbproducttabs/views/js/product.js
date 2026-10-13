/**
 * Product Extra Tabs - product page (back office)
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 */
(function () {
  'use strict';

  var root = document.getElementById('sbpt-product');
  if (!root || root.getAttribute('data-ready')) {
    return;
  }
  root.setAttribute('data-ready', '1');

  // Content of each textarea as it was when the page loaded (or once the editor normalized it)
  var originals = {};

  function notifyChange(element) {
    // Lets the product page know that the form has changed (enables the Save button)
    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function editorFor(textarea) {
    return window.tinymce && window.tinymce.get ? window.tinymce.get(textarea.id) : null;
  }

  function contentOf(textarea) {
    var editor = editorFor(textarea);

    return editor ? editor.getContent() : textarea.value;
  }

  function updateCard(card) {
    var dirty = false;

    Array.prototype.forEach.call(card.querySelectorAll('.sbpt-title'), function (input) {
      if (input.value !== input.getAttribute('data-original')) {
        dirty = true;
      }
    });
    Array.prototype.forEach.call(card.querySelectorAll('.sbpt-content'), function (textarea) {
      if (Object.prototype.hasOwnProperty.call(originals, textarea.id) && contentOf(textarea) !== originals[textarea.id]) {
        dirty = true;
      }
    });

    card.querySelector('.sbpt-dirty').value = dirty ? '1' : '0';
    card.classList.toggle('is-dirty', dirty);

    var scope = card.querySelector('.sbpt-scope');
    if (scope) {
      scope.hidden = !dirty;
    }
  }

  function initEditors() {
    var textareas = root.querySelectorAll('.sbpt-content');

    Array.prototype.forEach.call(textareas, function (textarea) {
      originals[textarea.id] = textarea.value;
      textarea.addEventListener('input', function () {
        updateCard(textarea.closest('.sbpt-card'));
      });
    });

    if (!window.tinymce || typeof window.tinymce.init !== 'function') {
      return false;
    }

    Array.prototype.forEach.call(textareas, function (textarea) {
      window.tinymce.init({
        target: textarea,
        menubar: false,
        branding: false,
        promotion: false,
        height: 240,
        entity_encoding: 'raw',
        convert_urls: false,
        plugins: 'link lists table code',
        toolbar: 'undo redo | bold italic underline | bullist numlist | link table | code',
        setup: function (editor) {
          editor.on('init', function () {
            // Compare against the editor's own formatting of the original content
            originals[textarea.id] = editor.getContent();
          });
          editor.on('change input keyup undo redo', function () {
            editor.save();
            updateCard(textarea.closest('.sbpt-card'));
            notifyChange(textarea);
          });
        },
      });
    });

    return true;
  }

  function waitForEditorLibrary(attempt) {
    if ((window.tinymce && typeof window.tinymce.init === 'function') || attempt > 40) {
      initEditors();
      return;
    }
    setTimeout(function () {
      waitForEditorLibrary(attempt + 1);
    }, 250);
  }

  // Language switch inside each card
  root.addEventListener('click', function (event) {
    var button = event.target.closest('.sbpt-lang');
    if (!button) {
      return;
    }
    var card = button.closest('.sbpt-card');
    var lang = button.getAttribute('data-lang');
    Array.prototype.forEach.call(card.querySelectorAll('.sbpt-lang'), function (b) {
      b.classList.toggle('is-active', b === button);
    });
    Array.prototype.forEach.call(card.querySelectorAll('.sbpt-lang-pane'), function (pane) {
      pane.hidden = pane.getAttribute('data-lang') !== lang;
    });
  });

  // Titles
  root.addEventListener('input', function (event) {
    if (event.target.classList.contains('sbpt-title')) {
      updateCard(event.target.closest('.sbpt-card'));
    }
  });

  // Show / hide on this product, replace with another tab
  root.addEventListener('change', function (event) {
    var target = event.target;
    var card = target.closest('.sbpt-card');

    if (target.classList.contains('sbpt-visible')) {
      card.classList.toggle('is-hidden', !target.checked);
    }
    if (target.classList.contains('sbpt-replace-select')) {
      var replaced = target.value !== '0';
      card.classList.toggle('is-replaced', replaced);
      card.querySelector('.sbpt-replace-note').hidden = !replaced;
    }
  });

  // Add existing tabs
  var addSelect = root.querySelector('.sbpt-add-select');
  var addButton = root.querySelector('.sbpt-add-button');
  var addList = root.querySelector('.sbpt-add-list');
  var addValue = root.querySelector('.sbpt-add-value');
  var addNote = root.querySelector('.sbpt-add-note');

  function refreshAddValue() {
    var ids = Array.prototype.map.call(addList.querySelectorAll('li'), function (li) {
      return li.getAttribute('data-id');
    });
    addValue.value = ids.join(',');
    addNote.hidden = ids.length === 0;
    notifyChange(addValue);
  }

  addButton.addEventListener('click', function () {
    var option = addSelect.options[addSelect.selectedIndex];
    if (!option || !option.value) {
      return;
    }
    var li = document.createElement('li');
    li.setAttribute('data-id', option.value);
    var label = document.createElement('span');
    label.textContent = option.textContent;
    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'sbpt-add-remove';
    remove.innerHTML = '&times;';
    remove.addEventListener('click', function () {
      option.disabled = false;
      li.parentNode.removeChild(li);
      refreshAddValue();
    });
    li.appendChild(label);
    li.appendChild(remove);
    addList.appendChild(li);

    option.disabled = true;
    addSelect.value = '';
    refreshAddValue();
  });

  // Make sure the editors' content is in the form when it is submitted
  var form = root.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      if (window.tinymce && typeof window.tinymce.triggerSave === 'function') {
        window.tinymce.triggerSave();
      }
    }, true);
  }

  waitForEditorLibrary(0);
})();
