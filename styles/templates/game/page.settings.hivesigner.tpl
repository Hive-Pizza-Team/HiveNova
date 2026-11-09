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
</form>
<script>
(function () {
	var statusEl = document.getElementById('hive-signer-status');
	var form = document.getElementById('hive-signer-form');
	var tokenInput = document.getElementById('hive-signer-token');
	var params = new URLSearchParams(window.location.hash.replace(/^#/, ''));
	if (!params.get('access_token') && window.location.search) {
		params = new URLSearchParams(window.location.search.replace(/^\?/, ''));
	}
	var token = params.get('access_token') || '';
	if (!token) {
		if (statusEl) {
			statusEl.textContent = "{$hiveSignerMissingToken|escape:'javascript'}";
		}
		return;
	}
	if (tokenInput && form) {
		tokenInput.value = token;
		form.submit();
	}
})();
</script>
{/block}
