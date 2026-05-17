{include file="overall_header.tpl"}
<form action="admin.php?page=dump" method="post">
<input type="hidden" name="action" value="dump">
<table class="table569">
	<tr>
		<th colspan="2">{$LNG.du_header}</th>
	</tr>
	<tr>
		<td>{$LNG.du_choose_tables}</td>
		<td>
            <div><input type="checkbox" id="selectAll"><label for="selectAll">{$LNG.du_select_all_tables}</label></div>
            <div>{html_options multiple="multiple" style="width:250px" size="10" name="dbtables[]" id="dbtables" values=$dumpData.sqlTables output=$dumpData.sqlTables}</div>
        </td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit" value="{$LNG.du_submit}"></td>
	</tr>
</table>
</form>
{if $dumpData.canRestore}
<form action="admin.php?page=dump" method="post" id="restoreForm">
<input type="hidden" name="action" value="restore">
<table class="table569" style="margin-top:1em;">
	<tr>
		<th colspan="2">{$LNG.du_restore_header}</th>
	</tr>
	<tr>
		<td colspan="2">{$LNG.du_restore_warning}</td>
	</tr>
	<tr>
		<td>{$LNG.du_restore_choose_file}</td>
		<td>
			{if $dumpData.backupFiles|@count > 0}
			<select name="backup_file" id="backup_file" style="width:100%;max-width:420px;">
				{foreach $dumpData.backupFiles as $backup}
				<option value="{$backup.file}">{$backup.label}</option>
				{/foreach}
			</select>
			{else}
			<p>{$LNG.du_restore_no_files}</p>
			{/if}
		</td>
	</tr>
	<tr>
		<td>{$LNG.du_restore_backup_before}</td>
		<td>
			<input type="hidden" name="backup_before" value="0">
			<label><input type="checkbox" name="backup_before" value="1" checked> {$LNG.du_restore_backup_before_label}</label>
		</td>
	</tr>
	<tr>
		<td>{$LNG.du_restore_confirm}</td>
		<td>
			<label><input type="checkbox" name="confirm_restore" value="1" id="confirm_restore"> {$LNG.du_restore_confirm_label}</label>
		</td>
	</tr>
	<tr>
		<td colspan="2">
			<input type="submit" value="{$LNG.du_restore_submit}" id="restoreSubmit" {if $dumpData.backupFiles|@count == 0}disabled="disabled"{/if}>
		</td>
	</tr>
</table>
</form>
{/if}
<script>
$(function() {
	$('#selectAll').on('click', function() {
		if($('#selectAll').prop('checked') === true)
		{
			$('#dbtables').val(function() {
				return $(this).children().map(function() { 
					return $(this).attr('value');
				}).toArray();
			});
		}
		else
		{
			$('#dbtables').val(null);
		}
	});

	$('#restoreForm').on('submit', function() {
		var fileName = $('#backup_file option:selected').text() || $('#backup_file').val();
		if(!$('#confirm_restore').prop('checked')) {
			alert('{$LNG.du_restore_not_confirmed|escape:'javascript'}');
			return false;
		}
		return confirm('{$LNG.du_restore_confirm_dialog|escape:'javascript'}'.replace('%s', fileName));
	});
});
</script>
{include file="overall_footer.tpl"}
