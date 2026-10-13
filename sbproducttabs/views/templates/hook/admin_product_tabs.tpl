{**
 * Product Extra Tabs - product page (back office)
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 *}
<link rel="stylesheet" href="{$sbpt_css|escape:'html':'UTF-8'}">
<div class="sbpt-product" id="sbpt-product" data-default-lang="{$sbpt_id_lang|intval}">
  <input type="hidden" name="sbpt[present]" value="1">
  <input type="hidden" name="sbpt[id_product]" value="{$sbpt_id_product|intval}">
  <input type="hidden" name="sbpt[add]" value="" class="sbpt-add-value">

  <div class="sbpt-intro">
    <p class="sbpt-help">
      {l s='Changes are saved together with the product when you click "Save".' mod='sbproducttabs'}
      <a href="{$sbpt_module_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Manage all tabs' mod='sbproducttabs'}</a>
    </p>
  </div>

  {if empty($sbpt_tabs)}
    <p class="sbpt-empty-list">{l s='No tab is shown on this product yet.' mod='sbproducttabs'}</p>
  {/if}

  {foreach from=$sbpt_tabs item=tab}
    {assign var=field value="sbpt[tabs][`$tab.id`]"}
    <div class="sbpt-card{if !$tab.visible} is-hidden{/if}" data-tab-id="{$tab.id|intval}" data-shared="{if $tab.shared}1{else}0{/if}">
      <input type="hidden" name="{$field|escape:'html':'UTF-8'}[dirty]" value="0" class="sbpt-dirty">

      <div class="sbpt-card-header">
        <div class="sbpt-card-title">
          <strong>{if $tab.internal_name}{$tab.internal_name|escape:'html':'UTF-8'}{else}{$tab.title[$sbpt_id_lang]|escape:'html':'UTF-8'}{/if}</strong>
          <span class="sbpt-id">#{$tab.id|intval}</span>
          <span class="sbpt-badge">{$tab.rule|escape:'html':'UTF-8'}</span>
          {if !$tab.active}<span class="sbpt-badge sbpt-badge-warning">{l s='Disabled tab' mod='sbproducttabs'}</span>{/if}
          <span class="sbpt-badge sbpt-badge-hidden">{l s='Hidden on this product' mod='sbproducttabs'}</span>
        </div>
        <div class="sbpt-card-actions">
          <label class="sbpt-switch">
            <input type="hidden" name="{$field|escape:'html':'UTF-8'}[visible]" value="0">
            <input type="checkbox" name="{$field|escape:'html':'UTF-8'}[visible]" value="1" class="sbpt-visible"{if $tab.visible} checked{/if}>
            <span>{l s='Show on this product' mod='sbproducttabs'}</span>
          </label>
          <a class="sbpt-link" href="{$tab.edit_url|escape:'html':'UTF-8'}" target="_blank" rel="noopener">{l s='Edit in module' mod='sbproducttabs'}</a>
        </div>
      </div>

      <div class="sbpt-card-body">
        {if $sbpt_multilang}
          <div class="sbpt-langs">
            {foreach from=$sbpt_languages item=lang}
              <button type="button" class="sbpt-lang{if $lang.id_lang == $sbpt_id_lang} is-active{/if}" data-lang="{$lang.id_lang|intval}">{$lang.iso_code|escape:'html':'UTF-8'}</button>
            {/foreach}
          </div>
        {/if}

        {foreach from=$sbpt_languages item=lang}
          {assign var=idl value=$lang.id_lang}
          <div class="sbpt-lang-pane" data-lang="{$idl|intval}"{if $idl != $sbpt_id_lang} hidden{/if}>
            <label class="sbpt-label">{l s='Title' mod='sbproducttabs'} <small>({$lang.iso_code|escape:'html':'UTF-8'})</small></label>
            <input type="text" class="form-control sbpt-title" maxlength="255"
                   name="{$field|escape:'html':'UTF-8'}[title][{$idl|intval}]"
                   value="{if isset($tab.title[$idl])}{$tab.title[$idl]|escape:'html':'UTF-8'}{/if}"
                   data-original="{if isset($tab.title[$idl])}{$tab.title[$idl]|escape:'html':'UTF-8'}{/if}">
            <label class="sbpt-label">{l s='Content' mod='sbproducttabs'} <small>({$lang.iso_code|escape:'html':'UTF-8'})</small></label>
            <textarea class="form-control sbpt-content" rows="8" id="sbpt-content-{$tab.id|intval}-{$idl|intval}"
                      name="{$field|escape:'html':'UTF-8'}[content][{$idl|intval}]">{if isset($tab.content[$idl])}{$tab.content[$idl]|escape:'html':'UTF-8'}{/if}</textarea>
          </div>
        {/foreach}

        {if $tab.shared}
          <div class="sbpt-scope" hidden>
            <p class="sbpt-warning">
              &#9888; {l s='This tab is also shown on other products' mod='sbproducttabs'}
              (<strong>{$tab.count|intval} {l s='products' mod='sbproducttabs'}</strong>).
              {l s='How should your changes be saved?' mod='sbproducttabs'}
            </p>
            <label class="sbpt-radio">
              <input type="radio" name="{$field|escape:'html':'UTF-8'}[scope]" value="product" checked>
              <span><strong>{l s='Only for this product' mod='sbproducttabs'}</strong> &ndash; {l s='a new tab is created for this product and the original one stops showing here.' mod='sbproducttabs'}</span>
            </label>
            <label class="sbpt-radio">
              <input type="radio" name="{$field|escape:'html':'UTF-8'}[scope]" value="all">
              <span><strong>{$tab.all_label|escape:'html':'UTF-8'}</strong></span>
            </label>
          </div>
        {/if}

        <div class="sbpt-replace">
          <label class="sbpt-label">{l s='Replace with another tab (only on this product)' mod='sbproducttabs'}</label>
          <select class="form-control sbpt-replace-select" name="{$field|escape:'html':'UTF-8'}[replace]">
            <option value="0">&mdash; {l s='Keep this tab' mod='sbproducttabs'} &mdash;</option>
            {foreach from=$sbpt_all_tabs item=option}
              {if $option.id != $tab.id}
                <option value="{$option.id|intval}">{$option.label|escape:'html':'UTF-8'}{if !$option.active} ({l s='disabled' mod='sbproducttabs'}){/if}</option>
              {/if}
            {/foreach}
          </select>
          <p class="sbpt-replace-note" hidden>{l s='This tab will be replaced on this product when you save.' mod='sbproducttabs'}</p>
        </div>
      </div>
    </div>
  {/foreach}

  <div class="sbpt-add">
    <label class="sbpt-label">{l s='Add an existing tab to this product' mod='sbproducttabs'}</label>
    <div class="sbpt-add-row">
      <select class="form-control sbpt-add-select">
        <option value="">&mdash; {l s='Choose a tab' mod='sbproducttabs'} &mdash;</option>
        {foreach from=$sbpt_addable_tabs item=option}
          <option value="{$option.id|intval}">{$option.label|escape:'html':'UTF-8'}{if !$option.active} ({l s='disabled' mod='sbproducttabs'}){/if}</option>
        {/foreach}
      </select>
      <button type="button" class="btn btn-outline-primary sbpt-add-button">{l s='Add' mod='sbproducttabs'}</button>
    </div>
    <ul class="sbpt-add-list"></ul>
    <p class="sbpt-add-note" hidden>{l s='These tabs will be added when you save the product.' mod='sbproducttabs'}</p>
  </div>
</div>
<script src="{$sbpt_js|escape:'html':'UTF-8'}"></script>
