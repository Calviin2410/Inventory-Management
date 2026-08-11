<script>
	import { goto } from '$app/navigation';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let sku = $state('');
	let name = $state('');
	let description = $state('');
	let costPrice = $state('');
	let sellPrice = $state('');
	let quantity = $state('0');
	let lowStockThreshold = $state('5');
	let barcode = $state('');

	let errorMessage = $state('');
	let submitting = $state(false);

	async function handleSubmit() {
		errorMessage = '';
		submitting = true;
		try {
			await api.createProduct({
				sku,
				name,
				description: description || null,
				cost_price: Number(costPrice || 0),
				sell_price: Number(sellPrice || 0),
				quantity: Number(quantity || 0),
				low_stock_threshold: Number(lowStockThreshold || 0),
				barcode: barcode || null
			});
			goto('/products');
		} catch (err) {
			errorMessage = err.message || '创建失败';
		} finally {
			submitting = false;
		}
	}
</script>

<Nav />

<div class="page">
	<h1>新增商品</h1>

	<form onsubmit={(event) => { event.preventDefault(); handleSubmit(); }}>
		{#if errorMessage}
			<p class="error">{errorMessage}</p>
		{/if}

		<label>
			SKU
			<input bind:value={sku} required />
		</label>

		<label>
			名称
			<input bind:value={name} required />
		</label>

		<label>
			描述
			<textarea bind:value={description} rows="2"></textarea>
		</label>

		<div class="row">
			<label>
				成本价
				<input type="number" min="0" step="0.01" bind:value={costPrice} required />
			</label>
			<label>
				售价
				<input type="number" min="0" step="0.01" bind:value={sellPrice} required />
			</label>
		</div>

		<div class="row">
			<label>
				初始库存
				<input type="number" min="0" bind:value={quantity} />
			</label>
			<label>
				低库存预警阈值
				<input type="number" min="0" bind:value={lowStockThreshold} />
			</label>
		</div>

		<label>
			条码(可选)
			<input bind:value={barcode} />
		</label>

		<button type="submit" disabled={submitting}>
			{submitting ? '提交中...' : '创建商品'}
		</button>
	</form>
</div>

<style>
	.page {
		max-width: 600px;
		margin: 2rem auto;
		padding: 0 1rem;
		font-family: sans-serif;
	}
	form {
		display: flex;
		flex-direction: column;
		gap: 1rem;
	}
	.row {
		display: flex;
		gap: 1rem;
	}
	.row label {
		flex: 1;
	}
	label {
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
		font-size: 0.9rem;
	}
	input,
	textarea {
		padding: 0.5rem;
		border: 1px solid #ccc;
		border-radius: 4px;
		font-family: inherit;
	}
	button {
		padding: 0.7rem;
		background: #2563eb;
		color: white;
		border: none;
		border-radius: 4px;
		cursor: pointer;
		font-size: 1rem;
	}
	button:disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	.error {
		color: #dc2626;
		font-size: 0.85rem;
	}
</style>
