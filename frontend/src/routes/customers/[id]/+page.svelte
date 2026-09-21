<script>
    import { onMount } from "svelte";
    import { page } from "$app/state";

    import Nav from "$lib/Nav.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import { api } from "$lib/api.js";
    import { formatDate } from "$lib/format.js";
    import { user } from "$lib/stores/auth.js";

    let customer = $state(null);
    let invoices = $state([]);
    let loading = $state(true);
    let errorMessage = $state("");

    let currentPage = $state(1);
    let lastPage = $state(1);
    let totalInvoices = $state(0);

    let isAdmin = $derived($user?.role === "admin");

    async function loadCustomer(pageNumber = 1) {
        loading = true;
        errorMessage = "";

        try {
            const result = await api.getCustomer(
                page.params.id,
                { page: pageNumber },
            );

            customer = result.customer;
            invoices = result.invoices?.data ?? [];
            currentPage = result.invoices?.current_page ?? 1;
            lastPage = result.invoices?.last_page ?? 1;
            totalInvoices = result.invoices?.total ?? 0;
        } catch (error) {
            errorMessage =
                error?.message || "Unable to load customer";
        } finally {
            loading = false;
        }
    }

    async function previousPage() {
        if (currentPage <= 1 || loading) {
            return;
        }

        await loadCustomer(currentPage - 1);
    }

    async function nextPage() {
        if (currentPage >= lastPage || loading) {
            return;
        }

        await loadCustomer(currentPage + 1);
    }

    onMount(() => loadCustomer());
</script>

<Nav />

<main class="app-page">
    <header class="page-heading">
        <div>
            <a href="/customers">← Back to customers</a>
            <p class="eyebrow">CUSTOMER HISTORY</p>
            <h1>{customer?.name ?? "Customer"}</h1>
            <p>{customer?.phone ?? "No phone number"}</p>
        </div>
    </header>

    <section class="panel">
        {#if loading}
            <SkeletonTable rows={5} columns={5} />
        {:else if errorMessage}
            <div class="state error">{errorMessage}</div>
        {:else if invoices.length === 0}
            <div class="state">
                No invoice history found.
            </div>
        {:else}
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice Number</th>
                            <th>Barrel Code</th>
                            <th>Invoice Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        {#each invoices as invoice (invoice.id)}
                            <tr>
                                <td class="strong">
                                    {invoice.invoice_no}
                                </td>

                                <td>
                                    {invoice.items
                                        ?.map((item) => item.barrel?.code)
                                        .filter(Boolean)
                                        .join(", ") || "—"}
                                </td>

                                <td>
                                    {formatDate(invoice.issued_date)}
                                </td>

                                <td>
                                    {invoice.status}
                                </td>

                                <td>
                                    {#if isAdmin}
                                        <a
                                            class="btn"
                                            href={`/invoices/${invoice.id}`}
                                        >
                                            View Invoice
                                        </a>
                                    {:else}
                                        —
                                    {/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                <div class="pagination-info">
                    Page {currentPage} of {lastPage}

                    <span>
                        {totalInvoices}
                        {totalInvoices === 1 ? "invoice" : "invoices"}
                    </span>
                </div>

                <div class="pagination-actions">
                    <button
                        type="button"
                        onclick={previousPage}
                        disabled={currentPage <= 1 || loading}
                    >
                        Previous
                    </button>

                    <button
                        type="button"
                        onclick={nextPage}
                        disabled={currentPage >= lastPage || loading}
                    >
                        Next
                    </button>
                </div>
            </div>
        {/if}
    </section>
</main>