<script>
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let products = [];
	let loading = true;
	let errorMessage = '';
	let search = '';

	// 入库/出库表单状态,按 product.id 索引
	let movementInputs = {};

	async function loadProducts() {
		loading = true;
		errorMessage = '';
		try {
			const data = await api.getProducts(search ? { search } : {});
			products = data.data; // Laravel paginate() 返回的数据在 .data 字段里
			// 为每个商品初始化一个空的入库/出库输入框状态
			for (const p of products) {
				if (!movementInputs[p.id]) movementInputs[p.id] = { quantity: '' };
			}
		} catch (err) {
			errorMessage = err.message || '加载失败,请确认已登录且后端服务正常';
		} finally {
			loading = false;
		}
	}

	async function submitMovement(product, type) {
		const qty = Number(movementInputs[product.id]?.quantity || 0);
		if (!qty || qty <= 0) return;

		try {
			const result = await api.createStockMovement(product.id, {
				type,
				quantity: qty,
				reason: type === 'in' ? '手动入库' : '手动出库'
			});
			// 更新本地列表里的库存数
			products = products.map((p) => (p.id === product.id ? result.product : p));
			movementInputs[product.id] = { quantity: '' };
		} catch (err) {
			alert(err.message || '操作失败');
		}
	}

	onMount(loadProducts);
</script>

<Nav />

<div class="page">
	<div class="header">
		<h1>商品与库存</h1>
		<a class="create-btn" href="/products/create">+ 新增商品</a>
	</div>

	<div class="toolbar">
		<input
			placeholder="搜索商品名称 / SKU / 条码"
			bind:value={search}
			on:keydown={(e) => e.key === 'Enter' && loadProducts()}
		/>
		<button on:click={loadProducts}>搜索</button>
	</div>

	{#if loading}
		<p>加载中...</p>
	{:else if errorMessage}
		<p class="error">{errorMessage}</p>
	{:else}
		<table>
			<thead>
				<tr>
					<th>SKU</th>
					<th>名称</th>
					<th>分类</th>
					<th>库存</th>
					<th>成本价</th>
					<th>售价</th>
					<th>库存操作</th>
				</tr>
			</thead>
			<tbody>
				{#each products as product (product.id)}
					<tr class:low={product.is_low_stock}>
						<td>{product.sku}</td>
						<td>{product.name}</td>
						<td>{product.category?.name ?? '-'}</td>
						<td>
							{product.quantity}
							{#if product.is_low_stock}
								<span class="badge">库存低</span>
							{/if}
						</td>
						<td>{product.cost_price}</td>
						<td>{product.sell_price}</td>
						<td class="movement-cell">
							<input
								type="number"
								min="1"
								placeholder="数量"
								bind:value={movementInputs[product.id].quantity}
							/>
							<button on:click={() => submitMovement(product, 'in')}>入库</button>
							<button on:click={() => submitMovement(product, 'out')}>出库</button>
						</td>
					</tr>
				{/each}
			</tbody>
		</table>
	{/if}
</div>

<style>
	.page {
		max-width: 960px;
		margin: 2rem auto;
		padding: 0 1rem;
		font-family: sans-serif;
	}
	.toolbar {
		display: flex;
		gap: 0.5rem;
		margin-bottom: 1rem;
	}
	.header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 1rem;
	}
	.create-btn {
		background: #2563eb;
		color: white;
		padding: 0.5rem 1rem;
		border-radius: 4px;
		text-decoration: none;
		font-size: 0.9rem;
	}
	.toolbar input {
		flex: 1;
		padding: 0.5rem;
		border: 1px solid #ccc;
		border-radius: 4px;
	}
	table {
		width: 100%;
		border-collapse: collapse;
	}
	th,
	td {
		text-align: left;
		padding: 0.5rem;
		border-bottom: 1px solid #eee;
		font-size: 0.9rem;
	}
	tr.low {
		background: #fef2f2;
	}
	.badge {
		display: inline-block;
		margin-left: 0.4rem;
		padding: 0.1rem 0.4rem;
		background: #dc2626;
		color: white;
		font-size: 0.7rem;
		border-radius: 4px;
	}
	.movement-cell {
		display: flex;
		gap: 0.3rem;
		align-items: center;
	}
	.movement-cell input {
		width: 60px;
		padding: 0.3rem;
	}
	.movement-cell button {
		padding: 0.3rem 0.5rem;
		cursor: pointer;
	}
	.error {
		color: #dc2626;
	}
</style>
