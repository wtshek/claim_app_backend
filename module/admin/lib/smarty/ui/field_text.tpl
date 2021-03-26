
    {if isset($multiple) && $multiple && is_array($value)}
        {foreach from=$value item=v}
            <div>
                {if $view_only}
                    <div class="input-text view form-control-plaintext">
                        <span>
                            {if $type == "password"}***{else}{$v|escape:'html'}{/if}
                        </span>
                    </div>
                {else}
                    <input {if $placeholder}placeholder="{if $placeholder != "1"}{$placeholder|escape:'html'}{else}{$title|escape:'html'}{/if}" {/if}type="{if $type == "password"}password{else}{if $type}{$type|escape:'html'}{else}text{/if}{/if}" name="{$name|escape:'html'}{if $multiple}[]{/if}"{if $id} id="{$id|escape:'html'}"{/if} value="{if $v === "" || is_null($v)}{$default|escape:'html'}{else}{$v|escape:'html'}{/if}"{if isset($maxlength)} maxlength="{$maxlength|escape:'html'}"{/if}{if isset($min)} min="{$min}"{/if}{if isset($max)} max="{$max}"{/if}{if isset($step)} step="{$step}"{/if} class="form-control {$class|escape:'html'}" {implode(' ', $extra)}>
                    {if isset($multiple) && $multiple}
                        <ul class="actions">
                            <li><input type="button" name="{$name|escape:'html'}_add" rel="add" value="+"></li>
                            <li><input type="button" name="{$name|escape:'html'}_remove" rel="remove" value="-"></li>
                        </ul>
                    {/if}
                {/if}
            </div>
        {/foreach}
    {else}
        <div>
            {if $view_only}
            <div class="input-text view">
                <span>
                    {if $type == "password"}***{else}{$value|escape:'html'}{/if}
                </span>
            </div>
            {else}
                <input {if $placeholder}placeholder="{if $placeholder != "1"}{$placeholder|escape:'html'}{else}{$title|escape:'html'}{/if}" {/if}type="{if $type == "password"}password{else}{if $type}{$type|escape:'html'}{else}text{/if}{/if}" name="{$name|escape:'html'}{if $multiple}[]{/if}"{if $id} id="{$id|escape:'html'}"{/if} value="{if $value === "" || is_null($value)}{$default|escape:'html'}{else}{$value|escape:'html'}{/if}"{if isset($maxlength)} maxlength="{$maxlength|escape:'html'}"{/if}{if isset($min)} min="{$min}"{/if}{if isset($max)} max="{$max}"{/if}{if isset($step)} step="{$step}"{/if} class="form-control {$class|escape:'html'}" {implode(' ', $extra)}{if $style} style="{$style|escape:'html'}"{/if}>
                {if isset($multiple) && $multiple}
                    <ul class="actions">
                        <li><input type="button" name="{$name|escape:'html'}_add" rel="add" value="+"></li>
                        <li><input type="button" name="{$name|escape:'html'}_remove" rel="remove" value="-"></li>
                    </ul>
                {/if}
            {/if}
        </div>
    {/if}
