/**
 * Product Extra Tabs - back office
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 */
(function () {
  'use strict';

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function config() {
    return window.sbProductTabs || { ajaxUrl: '', texts: {} };
  }

  function initPicker(picker) {
    var entity = picker.getAttribute('data-entity');
    var hidden = picker.querySelector('.sbpt-picker-value');
    var input = picker.querySelector('.sbpt-picker-input');
    var results = picker.querySelector('.sbpt-picker-results');
    var selected = picker.querySelector('.sbpt-picker-selected');
    var timer = null;
    var request = 0;

    function selectedIds() {
      return hidden.value.split(',').filter(function (id) { return id !== ''; });
    }

    function setIds(ids) {
      hidden.value = ids.join(',');
    }

    function closeResults() {
      results.hidden = true;
      results.innerHTML = '';
    }

    function addItem(item) {
      var ids = selectedIds();
      var id = String(item.id);
      if (ids.indexOf(id) !== -1) {
        return;
      }
      ids.push(id);
      setIds(ids);

      var li = document.createElement('li');
      li.className = 'sbpt-chip';
      li.setAttribute('data-id', id);
      li.innerHTML = '<span class="sbpt-chip-label">#' + escapeHtml(id) + ' ' + escapeHtml(item.name) +
        (item.reference ? ' <small>(' + escapeHtml(item.reference) + ')</small>' : '') + '</span>' +
        '<button type="button" class="sbpt-chip-remove" title="' + escapeHtml(config().texts.remove || 'Remove') + '">&times;</button>';
      selected.appendChild(li);
    }

    function renderResults(items) {
      results.innerHTML = '';
      var ids = selectedIds();
      var shown = items.filter(function (item) { return ids.indexOf(String(item.id)) === -1; });

      if (!shown.length) {
        results.innerHTML = '<li class="sbpt-empty">' + escapeHtml(config().texts.noResults || 'No results') + '</li>';
      }
      shown.forEach(function (item) {
        var li = document.createElement('li');
        li.className = 'sbpt-result';
        li.tabIndex = 0;
        li.innerHTML = '#' + escapeHtml(item.id) + ' ' + escapeHtml(item.name) +
          (item.reference ? ' <small>(' + escapeHtml(item.reference) + ')</small>' : '');
        li.addEventListener('mousedown', function (e) {
          e.preventDefault();
          addItem(item);
          input.value = '';
          closeResults();
          input.focus();
        });
        li.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            addItem(item);
            input.value = '';
            closeResults();
            input.focus();
          }
        });
        results.appendChild(li);
      });
      results.hidden = false;
    }

    function search() {
      var q = input.value.trim();
      if (!q) {
        closeResults();
        return;
      }
      var current = ++request;
      results.innerHTML = '<li class="sbpt-empty">' + escapeHtml(config().texts.searching || '...') + '</li>';
      results.hidden = false;

      var url = config().ajaxUrl + (config().ajaxUrl.indexOf('?') === -1 ? '?' : '&') +
        'ajax=1&action=search&entity=' + encodeURIComponent(entity) + '&q=' + encodeURIComponent(q);

      fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) { return response.json(); })
        .then(function (items) {
          if (current === request) {
            renderResults(Array.isArray(items) ? items : []);
          }
        })
        .catch(function () {
          if (current === request) {
            renderResults([]);
          }
        });
    }

    input.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(search, 250);
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        // Do not submit the whole form while searching
        e.preventDefault();
        search();
      } else if (e.key === 'ArrowDown') {
        var first = results.querySelector('.sbpt-result');
        if (first) {
          e.preventDefault();
          first.focus();
        }
      } else if (e.key === 'Escape') {
        closeResults();
      }
    });
    input.addEventListener('blur', function () {
      setTimeout(function () {
        if (!picker.contains(document.activeElement)) {
          closeResults();
        }
      }, 150);
    });

    selected.addEventListener('click', function (e) {
      var button = e.target.closest('.sbpt-chip-remove');
      if (!button) {
        return;
      }
      var chip = button.closest('.sbpt-chip');
      var id = chip.getAttribute('data-id');
      setIds(selectedIds().filter(function (value) { return value !== id; }));
      chip.parentNode.removeChild(chip);
    });
  }

  function initRuleToggle() {
    var select = document.querySelector('select[name="display_rule"]');
    if (!select) {
      return;
    }
    var pickers = document.querySelectorAll('.sbpt-picker');

    function update() {
      var rule = String(select.value);
      Array.prototype.forEach.call(pickers, function (picker) {
        var group = picker.closest('.form-group') || picker;
        var rules = (picker.getAttribute('data-rules') || '').split(',');
        group.style.display = rules.indexOf(rule) !== -1 ? '' : 'none';
      });
    }

    select.addEventListener('change', update);
    if (window.jQuery) {
      // Also catch changes triggered by select2/chosen widgets
      window.jQuery(select).on('change', update);
    }
    update();
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('.sbpt-picker'), initPicker);
    initRuleToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
