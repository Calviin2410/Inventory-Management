<script>
	import { onMount } from 'svelte';
	import { api } from '$lib/api.js';
	import Nav from '$lib/Nav.svelte';

	let recentInvoices = [];
	let loading = true;
	let errorMessage = '';

	onMount(async () => {
		try {
			recentInvoices = await api.getInvoices({ limit: 5 });
		} catch (err) {
			errorMessage = err.message || '加载失败';
		} finally {
			loading = false;
		}
	});
</script>

<Nav />

<div class="page">
	<h1>首页</h1>

	<section>
		<div class="section-header">
			<h2>最近的发票</h2>
			<a href="/invoices">查看全部 →</a>
		</div>

		{#if loading}
			<p>加载中...</p>
		{:else if errorMessage}
			<p class="error">{errorMessage}</p>
		{:else if recentInvoices.length === 0}
			<p class="empty">还没有发票记录。<a href="/invoices/create">创建第一张发票</a></p>
		{:else}
			<table>
				<thead>
					<tr>
						<th>发票号</th>
						<th>客户</th>
						<th>日期</th>
						<th>金额</th>
						<th>状态</th>
					</tr>
				</thead>
				<tbody>
					{#each recentInvoices as invoice (invoice.id)}
						<tr>
							<td>{invoice.invoice_no}</td>
							<td>{invoice.customer?.name ?? '-'}</td>
							<td>{invoice.issued_date}</td>
							<td>{invoice.total_amount}</td>
							<td>
								<span class="status {invoice.status}">
									{invoice.status === 'paid' ? '已付款' : '未付款'}
								</span>
							</td>
						</tr>
					{/each}
				</tbody>
			</table>
		{/if}
	</section>
</div>

<style>
	.page {
		max-width: 960px;
		margin: 2rem auto;
		padding: 0 1rem;
		font-family: sans-serif;
	}
	.section-header {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 0.75rem;
	}
	.section-header a {
		font-size: 0.85rem;
		color: #2563eb;
		text-decoration: none;
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
	.status {
		padding: 0.1rem 0.5rem;
		border-radius: 4px;
		font-size: 0.75rem;
	}
	.status.unpaid {
		background: #fef2f2;
		color: #dc2626;
	}
	.status.paid {
		background: #f0fdf4;
		color: #16a34a;
	}
	.empty {
		color: #64748b;
	}
	.error {
		color: #dc2626;
	}
</style>
