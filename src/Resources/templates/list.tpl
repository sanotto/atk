{if isset($formstart)}{$formstart}{/if}
<div>
    {if (isset($header) && !empty($header))}
        <div class="row list-header">
            <div class="col-md-12">{$header}</div>
        </div>
    {/if}
    {if (isset($index) && !empty($index))}
        <div class="row list-index">
            <div class="col-md-12">{$index}</div>
        </div>
    {/if}
    {if (isset($navbar) && !empty($navbar))}
        <div class="row list-navbar">
            <div class="col-md-12">{$navbar}</div>
        </div>
    {/if}
    <div class="row list-list">
        <div class="col-md-12">{$list}</div>
    </div>
    {if (isset($navbar) && !empty($navbar))}
        <div class="row list-navbar">
            <div class="col-md-12">{$navbar}</div>
        </div>
    {/if}
    {if (isset($footer) && !empty($footer))}
        <div class="row list-footer">
            <div class="col-md-12">{$footer}</div>
        </div>
    {/if}
</div>
{if isset($formstart)}{$formend}{/if}