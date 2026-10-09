{**
 * Product Extra Tabs
 *
 * @author    Sotiris Betsos
 * @copyright Sotiris Betsos
 *}
{extends file="helpers/form/form.tpl"}

{block name="input"}
    {if $input.type == 'sb_picker'}
        <div class="sbpt-picker" data-entity="{$input.entity|escape:'html':'UTF-8'}" data-rule="{$input.rule|intval}">
            <input type="hidden" name="{$input.name|escape:'html':'UTF-8'}" class="sbpt-picker-value" value="{$input.selected_ids|escape:'html':'UTF-8'}" />
            <div class="input-group sbpt-picker-search">
                <input type="text" class="form-control sbpt-picker-input" autocomplete="off" placeholder="{$input.placeholder|escape:'html':'UTF-8'}" />
                <span class="input-group-addon"><i class="icon-search"></i></span>
            </div>
            <ul class="sbpt-picker-results" hidden></ul>
            <ul class="sbpt-picker-selected">
                {foreach from=$input.selected item=item}
                    <li class="sbpt-chip" data-id="{$item.id|intval}">
                        <span class="sbpt-chip-label">#{$item.id|intval} {$item.name|escape:'html':'UTF-8'}{if !empty($item.reference)} <small>({$item.reference|escape:'html':'UTF-8'})</small>{/if}</span>
                        <button type="button" class="sbpt-chip-remove" title="{l s='Remove' mod='sbproducttabs'}">&times;</button>
                    </li>
                {/foreach}
            </ul>
        </div>
    {else}
        {$smarty.block.parent}
    {/if}
{/block}
