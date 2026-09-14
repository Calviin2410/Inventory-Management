<script>
	let { message = "", type = "success", onclose = () => {} } = $props();
</script>
{#if message}
	<div class="toast {type}" role="status" aria-live="polite">
		<span class="icon">{type === "success" ? "✓" : "!"}</span>
		<div><strong>{type === "success" ? "Success" : "Notice"}</strong><p>{message}</p></div>
		<button type="button" aria-label="Dismiss notification" onclick={onclose}>×</button>
	</div>
{/if}
<style>
	.toast { position: fixed; top: 80px; right: 22px; z-index: 900; display: flex; width: min(350px, calc(100vw - 32px)); align-items: center; gap: 11px; padding: 13px 14px; border: 1px solid #cfe9dc; border-radius: 10px; background: #f2fbf6; color: #186b48; box-shadow: 0 14px 35px rgb(15 23 42 / 14%); animation: enter .2s ease-out; }
	.icon { display: grid; width: 27px; height: 27px; place-items: center; flex: none; border-radius: 50%; background: #d7f3e4; font-size: 12px; font-weight: 800; }
	.toast div { min-width: 0; flex: 1; }
	strong, p { display: block; margin: 0; }
	strong { font-size: 10px; }
	p { margin-top: 2px; color: #4d7564; font-size: 10px; }
	button { border: 0; background: transparent; color: #719483; cursor: pointer; font-size: 18px; }
	@keyframes enter { from { opacity: 0; transform: translateY(-8px); } }
	@media (max-width: 820px) { .toast { top: 78px; right: 16px; left: 16px; width: auto; } }
</style>
