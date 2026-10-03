<script>
	let { visible = false } = $props();
</script>

<svg viewBox="0 0 24 24" aria-hidden="true">
	<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" />
	<circle cx="12" cy="12" r="2.7" />
	{#if !visible}
		<path d="m4 4 16 16" />
	{/if}
</svg>

<style>
	svg {
		display: block;
		width: 19px;
		height: 19px;
		fill: none;
		stroke: currentColor;
		stroke-width: 1.8;
		stroke-linecap: round;
		stroke-linejoin: round;
	}
</style>
