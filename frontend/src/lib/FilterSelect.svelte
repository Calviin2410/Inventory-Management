<script>
	let {
		value = $bindable(""),
		options = [],
		disabled = false,
		ariaLabel = "Select filter",
		onchange = undefined,
	} = $props();

	let selectedLabel = $derived(
		options.find((option) => option.value === value)?.label ?? options[0]?.label ?? "",
	);

	function preventDisabledToggle(event) {
		if (disabled) event.preventDefault();
	}

	function selectOption(option, event) {
		if (disabled) return;
		value = option.value;
		event.currentTarget.closest("details")?.removeAttribute("open");
		onchange?.(event);
	}
</script>

<details class:disabled class="filter-select">
	<summary
		class="control"
		aria-label={ariaLabel}
		aria-disabled={disabled}
		onclick={preventDisabledToggle}
	>
		<span>{selectedLabel}</span>
		<span class="chevron" aria-hidden="true"></span>
	</summary>
	<div class="options">
		{#each options as option}
			<button
				type="button"
				class:active={value === option.value}
				disabled={disabled}
				onclick={(event) => selectOption(option, event)}
			>
				{option.label}
			</button>
		{/each}
	</div>
</details>

<style>
	.filter-select {
		position: relative;
		width: 100%;
		min-width: 0;
	}

	summary {
		display: flex;
		width: 100%;
		box-sizing: border-box;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		cursor: pointer;
		list-style: none;
		user-select: none;
	}

	summary::-webkit-details-marker {
		display: none;
	}

	summary::marker {
		content: "";
	}

	.disabled summary {
		opacity: 0.65;
		cursor: not-allowed;
	}

	.chevron {
		width: 7px;
		height: 7px;
		flex: none;
		margin: -3px 2px 0 0;
		border-right: 1.5px solid currentColor;
		border-bottom: 1.5px solid currentColor;
		transform: rotate(45deg);
		transition: transform 0.15s ease;
	}

	.filter-select[open] .chevron {
		margin-top: 3px;
		transform: rotate(225deg);
	}

	.options {
		position: absolute;
		top: calc(100% + 6px);
		right: 0;
		left: 0;
		z-index: 1500;
		overflow: hidden;
		border: 1px solid #d7dce5;
		border-radius: 9px;
		background: white;
		box-shadow: 0 12px 28px rgb(15 23 42 / 16%);
	}

	button {
		display: block;
		width: 100%;
		padding: 10px 13px;
		border: 0;
		background: white;
		color: #202939;
		font: inherit;
		text-align: left;
		cursor: pointer;
	}

	button:hover,
	button:focus-visible,
	button.active {
		background: #eef5ff;
		color: #1d4ed8;
	}
</style>
