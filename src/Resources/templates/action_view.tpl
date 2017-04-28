{if isset($helplink)}
    <div id="action-helplink">
        {$helplink}<br/>
    </div>
{/if}
{$header}
<div id="action-content">
    {$content}
</div>
{$formstart}
<div id="action-buttons">
    <div class="action-buttons-buttons">
    {foreach from=$buttons item=button}
        &nbsp;{$button}&nbsp;
    {/foreach}
    </div>
    <div class="spinner"><i class="fa fa-cog fa-spin fa-2x"></i></div>
</div>
{$formend}
