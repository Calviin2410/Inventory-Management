<script>
	let { columns, visible = $bindable() } = $props();

	let allVisible = $derived(
		columns.every((column) => visible[column.key]),
	);

	function toggleColumn(key) {
		if (
			visible[key] &&
			columns.filter((column) => visible[column.key]).length === 1
		) {
			return;
		}

		visible = { ...visible, [key]: !visible[key] };
	}

	function showAllColumns() {
		visible = Object.fromEntries(columns.map((column) => [column.key, true]));
	}
</script>

<details class="column-picker">
	<summary>
		Columns <span class="select-chevron" aria-hidden="true"></span>
	</summary>
	<div class="column-menu">
		<label class="all-columns">
			<input type="checkbox" checked={allVisible} onchange={showAllColumns} />
			<span>All</span>
		</label>
		{#each columns as column (column.key)}
			<label>
				<input
					type="checkbox"
					checked={visible[column.key]}
					onchange={() => toggleColumn(column.key)}
				/>
				<span>{column.label}</span>
			</label>
		{/each}
	</div>
</details>

<style>
	.column-picker {
		position: relative;
		flex: none;
	}

	.column-picker summary {
		display: flex;
		min-width: 150px;
		min-height: 42px;
		align-items: center;
		justify-content: space-between;
		gap: 18px;
		padding: 0 13px;
		border: 1px solid #ccd4e0;
		border-radius: 8px;
		background: white;
		color: #172033;
		font-size: 13px;
		font-weight: 700;
		cursor: pointer;
		list-style: none;
	}

	.column-picker summary::-webkit-details-marker {
		display: none;
	}

	.select-chevron {
		width: 7px;
		height: 7px;
		flex: none;
		margin: -3px 2px 0 0;
		border-right: 1.5px solid currentColor;
		border-bottom: 1.5px solid currentColor;
		transform: rotate(45deg);
		transition: transform 0.15s ease;
	}

	.column-picker[open] summary {
		border-color: #4771e8;
		box-shadow: 0 0 0 3px rgb(53 99 233 / 12%);
	}

	.column-picker[open] .select-chevron {
		margin-top: 3px;
		transform: rotate(225deg);
	}

	.column-menu {
		position: absolute;
		top: calc(100% + 6px);
		right: 0;
		z-index: 20;
		display: grid;
		width: 230px;
		max-height: 340px;
		overflow-y: auto;
		padding: 7px;
		border: 1px solid #d7deea;
		border-radius: 9px;
		background: white;
		box-shadow: 0 14px 34px rgb(15 23 42 / 16%);
	}

	.column-menu label {
		display: flex;
		align-items: center;
		gap: 9px;
		padding: 9px 10px;
		border-radius: 6px;
		color: #273348;
		font-size: 13px;
		cursor: pointer;
	}

	.column-menu label:hover {
		background: #f4f7fb;
	}

	.column-menu input {
		width: 16px;
		height: 16px;
		margin: 0;
		accent-color: #315ee7;
	}

	.column-menu .all-columns {
		margin-bottom: 4px;
		border-bottom: 1px solid #e7ebf1;
		border-radius: 6px 6px 0 0;
		font-weight: 700;
	}

	@media (max-width: 900px) {
		.column-picker,
		.column-picker summary {
			width: 100%;
		}

		.column-menu {
			right: auto;
			left: 0;
			width: min(100%, 280px);
		}
	}
</style>
