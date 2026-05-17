{include file="overall_header.tpl"}

<div class="table569" style="margin-bottom:16px; padding:12px 16px; border:1px solid var(--color-border, #444); background:var(--color-surface-muted, #1a1a1a);">
    <strong style="display:block; margin-bottom:6px;">{$LNG.dest_story_title}</strong>
    <span style="line-height:1.45;">{$LNG.dest_story_body}</span>
</div>

<script type="text/javascript">
$(function() {
    function updateModeVisibility() {
        if ($('input[name=mode]:checked').val() === 'system') {
            $('#row_system').show();
        } else {
            $('#row_system').hide();
        }
        updateSystemFieldConstraint();
    }

    /** System coordinate only required in solar-system mode (hidden in galaxy mode must not block submit). */
    function updateSystemFieldConstraint() {
        var systemMode = $('input[name=mode]:checked').val() === 'system';
        var $sys = $('input[name=system]');
        if (systemMode) {
            $sys.attr({ min: 1, required: true });
        } else {
            $sys.removeAttr('min').removeAttr('required');
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
        updateRelocExactConstraints();
    }

    function updateRelocModeVisibility() {
        if ($('input[name=relocMode]:checked').val() === 'exact') {
            $('#reloc_exact_fields').show();
        } else {
            $('#reloc_exact_fields').hide();
        }
        updateRelocExactConstraints();
    }

    /** Relocation target numbers only required when relocate + exact; otherwise 0 must be submittable. */
    function updateRelocExactConstraints() {
        var relocOn = $('#relocate').prop('checked');
        var exact = $('input[name=relocMode]:checked').val() === 'exact';
        var needsExact = relocOn && exact;
        $('input[name=relocGal], input[name=relocSys], input[name=relocSlot]').each(function() {
            if (needsExact) {
                $(this).attr({ min: 1, required: true });
            } else {
                $(this).removeAttr('min').removeAttr('required');
            }
        });
    }

    /** Spawn G/S/P only validated in-browser when cursor update is enabled. */
    function updateSpawnFieldConstraints() {
        var on = $('#spawn_apply').prop('checked');
        $('input[name=spawn_galaxy], input[name=spawn_system], input[name=spawn_planet]').each(function() {
            if (on) {
                $(this).attr({ min: 1, required: true });
            } else {
                $(this).removeAttr('min').removeAttr('required');
            }
        });
    }

    $('input[name=mode]').on('change', updateModeVisibility);
    $('#relocate').on('change', updateRelocVisibility);
    $('input[name=relocMode]').on('change', updateRelocModeVisibility);
    $('#debris').on('change', updateDebrisVisibility);
    $('#spawn_apply').on('change', updateSpawnFieldConstraints);

    updateModeVisibility();
    updateRelocVisibility();
    updateDebrisVisibility();
    updateSpawnFieldConstraints();
});
</script>

{if $result !== null}
<br>
<table class="table569">
    <tr>
        <th colspan="2">{$LNG.dest_result_title}</th>
    </tr>
    {if $result.backup_path}
    <tr>
        <td colspan="2" style="color:#339966;">{$LNG.dest_backup_saved|sprintf:$result.backup_path}</td>
    </tr>
    {/if}
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
<div style="text-align:center; margin:18px 0;">
<form action="admin.php" method="get" style="display:inline;">
    <input type="hidden" name="page" value="destruction">
    <input type="hidden" name="sid" value="{$SID}">
    <button type="submit" style="padding:8px 22px; background-color:#336699; color:#fff; font-weight:bold; border:0; cursor:pointer;">{$LNG.dest_review_done_link}</button>
</form>
</div>

{elseif $reviewStage}
<br>
<table class="table569">
    <tr>
        <th colspan="2">{$LNG.dest_review_title}</th>
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
</table>

<br>
<table class="table569">
    <tr>
        <th colspan="2">{$LNG.dest_review_sql_title}</th>
    </tr>
    <tr>
        <td colspan="2">
            <pre style="white-space:pre-wrap; font-size:11px; max-height:280px; overflow:auto;">{foreach from=$sqlPreviewLines item=line}{$line|escape}
{/foreach}</pre>
        </td>
    </tr>
</table>

<br>
<form action="admin.php?page=destruction&amp;sid={$SID}" method="post">
    <input type="hidden" name="action" value="destroy">
    <input type="hidden" name="review_token" value="{$reviewToken|escape}">
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
    <input type="hidden" name="spawn_apply" value="{$spawn_apply}">
    <input type="hidden" name="spawn_galaxy" value="{$spawn_galaxy}">
    <input type="hidden" name="spawn_system" value="{$spawn_system}">
    <input type="hidden" name="spawn_planet" value="{$spawn_planet}">
    <table class="table569">
        <tr>
            <td>{$LNG.dest_backup_before}</td>
            <td>{$LNG.dest_backup_before_label}</td>
        </tr>
        <tr>
            <td colspan="2">
                <input type="submit" value="{$LNG.dest_review_execute}" style="background-color:#8b0000; color:#fff; font-weight:bold; padding:8px 22px;">
                &nbsp;&nbsp;
                <a href="admin.php?page=destruction&amp;action=cancel_review&amp;sid={$SID}" style="padding:8px 16px;">{$LNG.dest_review_cancel}</a>
            </td>
        </tr>
    </table>
</form>

{else}

{if $preview_error}
<br>
<table class="table569">
    <tr>
        <td colspan="2" style="color:#cc0000;">{$preview_error}</td>
    </tr>
</table>
{/if}

<form action="admin.php?page=destruction&amp;sid={$SID}" method="post" id="form_destruction_preview" novalidate>
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
        <td><input type="number" name="system" value="{$system}" step="1"></td>
    </tr>
    <tr>
        <td colspan="2"><strong>{$LNG.dest_spawn_section}</strong></td>
    </tr>
    <tr>
        <td>{$LNG.dest_spawn_apply}</td>
        <td>
            <input type="hidden" name="spawn_apply" value="0">
            <input type="checkbox" name="spawn_apply" value="1" id="spawn_apply" {if $spawn_apply}checked{/if} title="{$LNG.dest_spawn_apply|escape:'html'}">
        </td>
    </tr>
    <tr>
        <td colspan="2" valign="top" style="font-size:11px; line-height:1.45; opacity:0.95;">{$LNG.dest_spawn_help}</td>
    </tr>
    <tr>
        <td style="padding-top:8px;"><strong>{$LNG.dest_spawn_coords_heading}</strong></td>
        <td style="padding-top:8px;"></td>
    </tr>
    <tr id="row_spawn_coords">
        <td>{$LNG.dest_spawn_coords}</td>
        <td>
            {$LNG.dest_galaxy}: <input type="number" name="spawn_galaxy" value="{$spawn_galaxy}" style="width:70px" step="1">
            &nbsp; {$LNG.dest_system}: <input type="number" name="spawn_system" value="{$spawn_system}" style="width:70px" step="1">
            &nbsp; {$LNG.dest_slot}: <input type="number" name="spawn_planet" value="{$spawn_planet}" style="width:70px" step="1">
        </td>
    </tr>
    <tr>
        <td colspan="2"><strong>{$LNG.dest_spawn_section}</strong></td>
    </tr>
    <tr>
        <td>{$LNG.dest_spawn_apply}</td>
        <td>
            <input type="hidden" name="spawn_apply" value="0">
            <input type="checkbox" name="spawn_apply" value="1" id="spawn_apply" {if $spawn_apply}checked{/if} title="{$LNG.dest_spawn_apply|escape:'html'}">
        </td>
    </tr>
    <tr>
        <td colspan="2" valign="top" style="font-size:11px; line-height:1.45; opacity:0.95;">{$LNG.dest_spawn_help}</td>
    </tr>
    <tr>
        <td style="padding-top:8px;"><strong>{$LNG.dest_spawn_coords_heading}</strong></td>
        <td style="padding-top:8px;"></td>
    </tr>
    <tr id="row_spawn_coords">
        <td>{$LNG.dest_spawn_coords}</td>
        <td>
            {$LNG.dest_galaxy}: <input type="number" name="spawn_galaxy" min="1" value="{$spawn_galaxy}" style="width:70px">
            &nbsp; {$LNG.dest_system}: <input type="number" name="spawn_system" min="1" value="{$spawn_system}" style="width:70px">
            &nbsp; {$LNG.dest_slot}: <input type="number" name="spawn_planet" min="1" value="{$spawn_planet}" style="width:70px">
        </td>
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
                {$LNG.dest_galaxy}: <input type="number" name="relocGal" value="{$relocGal}" style="width:60px" step="1">
                &nbsp;
                {$LNG.dest_system}: <input type="number" name="relocSys" value="{$relocSys}" style="width:60px" step="1">
                &nbsp;
                {$LNG.dest_slot}: <input type="number" name="relocSlot" value="{$relocSlot}" style="width:60px" step="1">
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
            <form action="admin.php?page=destruction&amp;sid={$SID}" method="post" style="display:inline;">
                <input type="hidden" name="action" value="accept_review">
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
                <input type="hidden" name="spawn_apply" value="{$spawn_apply}">
                <input type="hidden" name="spawn_galaxy" value="{$spawn_galaxy}">
                <input type="hidden" name="spawn_system" value="{$spawn_system}">
                <input type="hidden" name="spawn_planet" value="{$spawn_planet}">
                <input type="submit" value="{$LNG.dest_preview_continue}" style="background-color:#336699; color:#fff; font-weight:bold; padding:6px 18px;">
            </form>
            &nbsp;&nbsp;
            <a href="admin.php?page=destruction&amp;action=cancel_preview&amp;sid={$SID}" style="padding:6px 14px;">{$LNG.dest_preview_discard}</a>
        </td>
    </tr>
    {/if}
</table>
{/if}

{/if}

{include file="overall_footer.tpl"}
