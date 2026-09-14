<script>
	let { rows = 5, columns = 4 } = $props();
</script>
<div class="skeleton" aria-label="Loading content" aria-busy="true">
	<div class="skeleton-head"></div>
	{#each Array(rows) as _}
		<div class="skeleton-row" style:grid-template-columns={`repeat(${columns}, minmax(0, 1fr))`}>
			{#each Array(columns) as _, index}<span class:short={index === columns - 1}></span>{/each}
		</div>
	{/each}
</div>
<style>
	.skeleton { overflow: hidden; border-radius: 10px; background: white; }
	.skeleton-head { height: 43px; background: #f0f3f7; }
	.skeleton-row { display: grid; gap: 22px; padding: 18px; border-top: 1px solid #edf0f4; }
	.skeleton-row span { height: 10px; border-radius: 5px; background: linear-gradient(90deg, #edf0f4 25%, #f7f8fa 50%, #edf0f4 75%); background-size: 200% 100%; animation: shimmer 1.25s infinite linear; }
	.skeleton-row span.short { width: 55%; }
	@keyframes shimmer { to { background-position: -200% 0; } }
</style>
