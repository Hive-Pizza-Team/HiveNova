<button type="button" id="feedback-fab" class="feedback-fab" aria-haspopup="dialog" aria-controls="feedback-modal" title="{$LNG.fb_button}">
	<span class="feedback-fab__icon" aria-hidden="true">?</span>
	<span class="feedback-fab__label">{$LNG.fb_button}</span>
</button>

<dialog id="feedback-modal" class="feedback-modal" aria-labelledby="feedback-modal-title">
	<form id="feedback-form" class="feedback-modal__form" method="post" enctype="multipart/form-data" action="{$feedbackConfig.submitUrl|escape:'html'}">
		<header class="feedback-modal__header">
			<h2 id="feedback-modal-title">{$LNG.fb_title}</h2>
			<button type="button" class="feedback-modal__close" data-feedback-close aria-label="{$LNG.fb_close}">&times;</button>
		</header>
		<p class="feedback-modal__intro">{$LNG.fb_intro}</p>
		<label class="feedback-modal__field">
			<span>{$LNG.fb_type}</span>
			<select name="type" id="feedback-type">
				<option value="bug">{$LNG.fb_type_bug}</option>
				<option value="idea">{$LNG.fb_type_idea}</option>
				<option value="other" selected>{$LNG.fb_type_other}</option>
			</select>
		</label>
		<label class="feedback-modal__field">
			<span>{$LNG.fb_description} <abbr title="{$LNG.fb_required}">*</abbr></span>
			<textarea name="description" id="feedback-description" rows="5" required maxlength="8000" placeholder="{$LNG.fb_description_placeholder}"></textarea>
		</label>
		<label class="feedback-modal__field feedback-modal__checkbox">
			<input type="checkbox" name="captureScreenshot" id="feedback-capture-screenshot" value="1">
			<span>{$LNG.fb_capture_screenshot}</span>
		</label>
		<label class="feedback-modal__field">
			<span>{$LNG.fb_attach_screenshot}</span>
			<input type="file" name="screenshot" id="feedback-screenshot" accept="image/png,image/jpeg,image/gif,image/webp">
		</label>
		<details class="feedback-modal__context" open>
			<summary>{$LNG.fb_context_heading}</summary>
			<pre id="feedback-context-preview" class="feedback-modal__context-body"></pre>
		</details>
		<p id="feedback-status" class="feedback-modal__status" role="status" aria-live="polite" hidden></p>
		<footer class="feedback-modal__actions">
			<button type="button" class="button" data-feedback-close>{$LNG.fb_cancel}</button>
			<button type="submit" class="button" id="feedback-submit">{$LNG.fb_send}</button>
		</footer>
		<input type="hidden" name="csrf" id="feedback-csrf" value="{$feedbackConfig.csrf|escape:'html'}">
		<input type="hidden" name="pageUrl" id="feedback-page-url" value="">
		<input type="hidden" name="viewport" id="feedback-viewport" value="">
	</form>
</dialog>

<script type="application/json" id="feedback-config-json">{$feedbackConfig|json}</script>
<script src="scripts/game/feedback.js?v={$REV}" defer></script>
