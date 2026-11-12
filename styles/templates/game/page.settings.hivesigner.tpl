{block name="title" prepend}{$LNG.op_hive_link_hivesigner}{/block}
{block name="content"}
<table>
	<tr>
		<th>{$LNG.op_hive_link_hivesigner}</th>
	</tr>
	<tr>
		<td id="hive-signer-status">{$hiveSignerWorking}</td>
	</tr>
</table>
<form id="hive-signer-form" action="{$hiveSignerPostUrl}" method="post">
	<input type="hidden" name="access_token" id="hive-signer-token" value="">
	<input type="hidden" name="state" id="hive-signer-state" value="">
</form>
<script>
(function () {
	var statusEl = document.getElementById('hive-signer-status');
	var form = document.getElementById('hive-signer-form');
	var tokenInput = document.getElementById('hive-signer-token');
	var stateInput = document.getElementById('hive-signer-state');
	var params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
	var token = params.get('access_token') || '';
	var state = params.get('state') || '';
	if (!token || !state) {
		if (statusEl) {
			statusEl.textContent = "{$hiveSignerMissingToken|escape:'javascript'}";
		}
		return;
	}
	if (tokenInput && stateInput && form) {
		tokenInput.value = token;
		stateInput.value = state;
		form.submit();
	}
})();
</script>
{/block}
