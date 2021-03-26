{if $view_only}
<div class="input-select view form-control-plaintext">
    <span>
    	{if $multiple}
    		{if $selected|@count>0}
    			{foreach $selected as $item}
    				{$options[$item]|escape:'html'}{if !$item@last}, {/if}
    			{/foreach}
    		{else}
    			--
    		{/if}
    	{else}
       		{if isset($options[$selected]) && $options[$selected]}{$options[$selected]|escape:'html'}{else}--{/if}
       	{/if}
    </span>
</div>
{else}
<select name="{$name|escape:'html'}"{if $id} id="{$id|escape:'html'}"{/if}{if isset($multiple) && $multiple} multiple="multiple"{/if} class="form-control {$class|escape:'html'}" {if $style} style="{$style|escape:'html'}"{/if}>
    {if $has_empty}<option value="">&nbsp;</option>{/if}
    {foreach from=$options key=k item=v}
        <option value="{$k|escape:'html'}"{if isset($selected) && $selected !== "" && ($selected==$k || (is_array($selected) && in_array($k, $selected)))} selected{/if}{if in_array($k, $disabled_items)} disabled="disabled"{/if}>{$v|escape:'html'}</option>
    {/foreach}
</select>
{/if}