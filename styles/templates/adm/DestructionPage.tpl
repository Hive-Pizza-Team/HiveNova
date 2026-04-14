{include file="overall_header.tpl"}
<script type="text/javascript">
$(function() {
    function updateModeVisibility() {
        if ($('input[name=mode]:checked').val() === 'system') {
            $('#row_system').show();
        } else {
            $('#row_system').hide();
        }
    }

    function updateDebrisVisibility() {
        if ($('#debris').prop('checked')) {
            $('#row_debris_amounts').show();
        } else {
            $('#row_debris_amounts').hide();
        }
    }

    function updateRelocVisibility() {
        if ($('#relocate').prop('checked')) {
            $('#row_reloc_opts').show();
            updateRelocModeVisibility();
        } else {
            $('#row_reloc_opts').hide();
        }
    }

    function updateRelocModeVisibility() {
        if ($('input[name=relocMode]:checked').val() === 'exact') {
            $('#reloc_exact_fields').show();
        } else {
            $('#reloc_exact_fields').hide();
        }
    }

    $('input[name=mode]').on('change', updateModeVisibility);
    $('#relocate').on('change', updateRelocVisibility);
    $('input[name=relocMode]').on('change', updateRelocModeVisibility);
    $('#debris').on('change', updateDebrisVisibility);

    updateModeVisibility();
    updateRelocVisibility();
    updateDebrisVisibility();
});
</script>

<form action="admin.php?page=destruction&sid={$SID}" method="post">
<input type="hidden" name="action" value="preview">
<table class="table569">
    <tr>
        <th colspan="2">{$LNG.dest_title}</th>
    </tr>
    <tr>
        <td>{$LNG.dest_universe}</td>
        <td>{html_options name=universe values=$universeList output=$universeList selected=$universe}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_mode}</td>
        <td>
            <label><input type="radio" name="mode" value="system" {if $mode === 'system'}checked{/if}> {$LNG.dest_mode_system}</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="mode" value="galaxy" {if $mode === 'galaxy'}checked{/if}> {$LNG.dest_mode_galaxy}</label>
        </td>
    </tr>
    <tr>
        <td>{$LNG.dest_galaxy}</td>
        <td><input type="number" name="galaxy" min="1" value="{$galaxy}"></td>
    </tr>
    <tr id="row_system">
        <td>{$LNG.dest_system}</td>
        <td><input type="number" name="system" min="1" value="{$system}"></td>
    </tr>
    <tr>
        <td>{$LNG.dest_relocate}</td>
        <td>
            <input type="checkbox" name="relocate" value="1" id="relocate" {if $relocate}checked{/if}>
            <label for="relocate">{$LNG.dest_relocate_label}</label>
        </td>
    </tr>
    <tr id="row_reloc_opts">
        <td>{$LNG.dest_reloc_target}</td>
        <td>
            <label><input type="radio" name="relocMode" value="random" {if $relocMode === 'random'}checked{/if}> {$LNG.dest_reloc_random}</label>
            <br>
            <label><input type="radio" name="relocMode" value="exact" {if $relocMode === 'exact'}checked{/if}> {$LNG.dest_reloc_exact}</label>
            <div id="reloc_exact_fields" style="margin-top:4px; margin-left:20px;">
                {$LNG.dest_galaxy}: <input type="number" name="relocGal" min="1" value="{$relocGal}" style="width:60px">
                &nbsp;
                {$LNG.dest_system}: <input type="number" name="relocSys" min="1" value="{$relocSys}" style="width:60px">
                &nbsp;
                {$LNG.dest_slot}: <input type="number" name="relocSlot" min="1" value="{$relocSlot}" style="width:60px">
            </div>
        </td>
    </tr>
    <tr>
        <td>{$LNG.dest_debris}</td>
        <td><input type="checkbox" name="debris" value="1" id="debris" {if $debris}checked{/if}> <label for="debris">{$LNG.dest_debris_label}</label></td>
    </tr>
    <tr id="row_debris_amounts">
        <td>{$LNG.dest_debris_amounts}</td>
        <td>
            {$LNG.dest_debris_metal}: <input type="number" name="debris_metal" min="0" value="{$debris_metal}" style="width:120px">
            &nbsp;
            {$LNG.dest_debris_crystal}: <input type="number" name="debris_crystal" min="0" value="{$debris_crystal}" style="width:120px">
        </td>
    </tr>
    <tr>
        <td>{$LNG.dest_broadcast}</td>
        <td><input type="checkbox" name="broadcast" value="1" {if $broadcast}checked{/if}> <label>{$LNG.dest_broadcast_label}</label></td>
    </tr>
    <tr>
        <td>{$LNG.dest_message}</td>
        <td><textarea name="message" cols="50" rows="8">{$message|escape}</textarea></td>
    </tr>
    <tr>
        <td colspan="2"><input type="submit" value="{$LNG.dest_preview_btn}"></td>
    </tr>
</table>
</form>

{if $preview !== null}
<br>
<table class="table569">
    <tr>
        <th colspan="2">{$LNG.dest_preview_title}</th>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_planets}</td>
        <td>{$preview.planets}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_players}</td>
        <td>{$preview.players}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_fleets_lost}</td>
        <td>{$preview.fleets_lost}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_fleets_survive}</td>
        <td>{$preview.fleets_survive}</td>
    </tr>
    {if $preview.homeless > 0}
    <tr>
        <td colspan="2" style="color:#cc0000;">
            {$LNG.dest_preview_homeless_warning|sprintf:$preview.homeless}
        </td>
    </tr>
    {/if}
    {if $preview.planets === 0}
    <tr>
        <td colspan="2" style="color:#cc0000;">{$LNG.dest_preview_empty}</td>
    </tr>
    {else}
    <tr>
        <td colspan="2">
            <form action="admin.php?page=destruction&sid={$SID}" method="post">
                <input type="hidden" name="action" value="destroy">
                <input type="hidden" name="universe" value="{$universe}">
                <input type="hidden" name="mode" value="{$mode}">
                <input type="hidden" name="galaxy" value="{$galaxy}">
                <input type="hidden" name="system" value="{$system}">
                <input type="hidden" name="relocate" value="{$relocate}">
                <input type="hidden" name="relocMode" value="{$relocMode}">
                <input type="hidden" name="relocGal" value="{$relocGal}">
                <input type="hidden" name="relocSys" value="{$relocSys}">
                <input type="hidden" name="relocSlot" value="{$relocSlot}">
                <input type="hidden" name="debris" value="{$debris}">
                <input type="hidden" name="debris_metal" value="{$debris_metal}">
                <input type="hidden" name="debris_crystal" value="{$debris_crystal}">
                <input type="hidden" name="broadcast" value="{$broadcast}">
                <input type="hidden" name="message" value="{$message|escape:'html'}">
                <input type="submit" value="{$LNG.dest_confirm_destroy}" style="background-color:#8b0000; color:#fff; font-weight:bold; padding:6px 18px;">
            </form>
        </td>
    </tr>
    {/if}
</table>
{/if}

{if $result !== null}
<br>
<table class="table569">
    <tr>
        <th colspan="2">{$LNG.dest_result_title}</th>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_planets}</td>
        <td>{$result.planets}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_fleets_lost}</td>
        <td>{$result.fleets_lost}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_fleets_survive}</td>
        <td>{$result.fleets_survived}</td>
    </tr>
    <tr>
        <td>{$LNG.dest_preview_players}</td>
        <td>{$result.relocated}</td>
    </tr>
    {if $result.skipped > 0}
    <tr>
        <td colspan="2" style="color:#cc0000;">
            {$LNG.dest_result_skipped|sprintf:$result.skipped}
        </td>
    </tr>
    {/if}
    <tr>
        <td colspan="2" style="color:#00aa00; font-weight:bold;">{$LNG.dest_result_done}</td>
    </tr>
</table>
{/if}

{include file="overall_footer.tpl"}
