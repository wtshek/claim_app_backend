{if $wrap && $placeholder !== true}
<dl class="input-field {$field_type|escape:'html'}-field{if isset($multiple) && $multiple} multiple-field{/if}{if $hasError} hasError{/if} row {if $view_only}view{else}edit{/if}" name="{$name|escape:'html'}_input" {if $type=="hidden"}style="display:none;"{/if}>
    {if $title}
        <dt class="col-12 col-lg-{$ratio_title}">{if !$view_only}<label for="{$id}">{/if}{$title|escape:'html'}{if isset($required) && $required} <span class="required">*</span>{/if}{if !$view_only}</label>{/if}</dt>
    {/if}
	{if $ratio_content!='-1'}
    <dd class="col-12 col-lg-{$ratio_content}">
	{/if}
{else}
{if $ratio_content!='-1'}
<div class="input-field {$field_type|escape:'html'}-field{if isset($multiple) && $multiple} multiple-field{/if}{if $hasError} hasError{/if}">
{/if}
{/if}
	{if $ratio_content!='-1'}
    {$content}
    {if $hasError}
    <div class="error">
        <span>{$errorMsg|escape:'html'}</span>
    </div>
    {/if}
	{/if}
{if $wrap && $placeholder !== true && $ratio_content!='-1'}
    </dd>
</dl>
{else}
{if $ratio_content!='-1'}
</div>
{/if}
{/if}