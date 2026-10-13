/**
 * Product Extra Tabs - product page (front office)
 *
 * Hides the selected default tabs and opens the first visible tab,
 * for tab based themes (Classic and most themes) and accordion based ones (Hummingbird).
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 */
(function () {
  'use strict';

  var config = window.sbProductTabsFront || { hide: [], focusFirst: true };
  var hidden = config.hide || [];

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function targetId(trigger) {
    var value = trigger.getAttribute('data-bs-target') || trigger.getAttribute('data-target') || trigger.getAttribute('href') || '';

    return value.charAt(0) === '#' ? value.substring(1) : '';
  }

  function isHiddenId(id) {
    return id !== '' && hidden.indexOf(id) !== -1;
  }

  function isVisible(element) {
    return !!element && element.style.display !== 'none' && window.getComputedStyle(element).display !== 'none';
  }

  /* ---------- Tabs (Classic and most themes) ---------- */

  function tabTriggers(tabList) {
    return toArray(tabList.querySelectorAll('[data-toggle="tab"], [data-bs-toggle="tab"], [role="tab"]'))
      .filter(function (trigger) {
        return targetId(trigger) !== '';
      });
  }

  function triggerItem(trigger) {
    var item = trigger.closest('li, .nav-item');

    return item && item.contains(trigger) ? item : trigger;
  }

  function hideTabs(tabList) {
    tabTriggers(tabList).forEach(function (trigger) {
      var id = targetId(trigger);
      if (isHiddenId(id)) {
        triggerItem(trigger).style.display = 'none';
        var pane = document.getElementById(id);
        if (pane) {
          pane.style.display = 'none';
        }
      }
    });
  }

  function activateTab(tabList, trigger) {
    // Bootstrap 3 style themes mark the <li> as active, Bootstrap 4 / 5 ones only the link
    var activeOnItem = tabTriggers(tabList).some(function (other) {
      var item = triggerItem(other);

      return item !== other && item.classList.contains('active');
    });

    tabTriggers(tabList).forEach(function (other) {
      var pane = document.getElementById(targetId(other));
      var active = other === trigger;

      other.classList.toggle('active', active);
      other.classList.toggle('js-product-nav-active', active);
      other.setAttribute('aria-selected', active ? 'true' : 'false');
      var item = triggerItem(other);
      if (activeOnItem && item !== other) {
        item.classList.toggle('active', active);
      }
      if (pane) {
        ['active', 'show', 'in', 'js-product-tab-active'].forEach(function (cls) {
          pane.classList.toggle(cls, active);
        });
      }
    });
  }

  function focusFirstTab(tabList) {
    var visible = tabTriggers(tabList).filter(function (trigger) {
      return !isHiddenId(targetId(trigger)) && isVisible(triggerItem(trigger)) && isVisible(trigger);
    });
    if (visible.length) {
      activateTab(tabList, visible[0]);
    }
  }

  /* ---------- Accordion (Hummingbird) ---------- */

  function accordionTriggers(accordion) {
    return toArray(accordion.querySelectorAll('[data-bs-toggle="collapse"], [data-toggle="collapse"]'))
      .filter(function (trigger) {
        return targetId(trigger) !== '';
      });
  }

  function hideAccordion(accordion) {
    accordionTriggers(accordion).forEach(function (trigger) {
      if (isHiddenId(targetId(trigger))) {
        var item = trigger.closest('.accordion-item') || document.getElementById(targetId(trigger));
        if (item) {
          item.style.display = 'none';
        }
      }
    });
  }

  function focusFirstAccordion(accordion) {
    var first = accordionTriggers(accordion).filter(function (trigger) {
      var item = trigger.closest('.accordion-item');

      return !isHiddenId(targetId(trigger)) && (!item || isVisible(item));
    })[0];
    if (!first) {
      return;
    }
    var panel = document.getElementById(targetId(first));
    if (panel && !panel.classList.contains('show')) {
      panel.classList.add('show');
      first.classList.remove('collapsed');
      first.setAttribute('aria-expanded', 'true');
    }
  }

  /* ---------- Run ---------- */

  function tabLists() {
    // Tab lists that control panes of the product page (description, details, module tabs...)
    return toArray(document.querySelectorAll('.nav-tabs, [role="tablist"]')).filter(function (list) {
      return tabTriggers(list).some(function (trigger) {
        var id = targetId(trigger);

        return isHiddenId(id) || /^(description|product-details|attachments|extra-\d+)$/.test(id);
      });
    });
  }

  function accordions() {
    return toArray(document.querySelectorAll('#product_accordion, .product__accordion'));
  }

  function run(focus) {
    tabLists().forEach(function (list) {
      hideTabs(list);
      if (focus) {
        focusFirstTab(list);
      }
    });
    accordions().forEach(function (accordion) {
      hideAccordion(accordion);
      if (focus) {
        focusFirstAccordion(accordion);
      }
    });
  }

  function init() {
    run(!!config.focusFirst);

    // Parts of the product page are re-rendered when a combination is selected
    if (window.prestashop && typeof window.prestashop.on === 'function') {
      window.prestashop.on('updatedProduct', function () {
        run(false);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
