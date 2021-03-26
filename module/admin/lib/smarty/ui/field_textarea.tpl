{if isset($multiple) && $multiple && is_array($value)}

{else}
    <div style="">
        {if $view_only}
            <div class="input-text view form-control-plaintext">
                <span>
                    {if $type == "password"}*{else}{$value|escape|nl2br}{/if}
                </span>
            </div>
        {else}
            <textarea {if $placeholder}placeholder="{if $placeholder != "1"}{$placeholder|escape}{else}{$title|escape}{/if}" {/if}{if $maxlength} maxlength="{$maxlength|escape}"{/if} name="{$name|escape}{if $multiple}[]{/if}"{if $id} id="{$id|escape}"{/if} class="form-control {$class|escape}" {implode(' ', $extra)}{if isset($style)} style="{$style}"{/if}{if isset($rows)}rows="{$rows}"{/if}{if isset($cols)}cols="{$cols}"{/if}>{if $value}{$value|escape}{/if}</textarea>
        {/if}
    </div>
{/if}
