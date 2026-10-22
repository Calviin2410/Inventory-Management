<script>
    import { onMount } from "svelte";
    import { page } from "$app/state";

    import Nav from "$lib/Nav.svelte";
    import SkeletonTable from "$lib/SkeletonTable.svelte";
    import FilterSelect from "$lib/FilterSelect.svelte";
    import { api } from "$lib/api.js";
    import { formatDate } from "$lib/format.js";
    import { user } from "$lib/stores/auth.js";
    import ColumnPicker from "$lib/ColumnPicker.svelte";

    const customerInvoiceColumns = [
        { key: "invoice", label: "Invoice Number" },
        { key: "barrels", label: "Barrel Code" },
        { key: "date", label: "Invoice Date" },
        { key: "status", label: "Status" },
    ];
    let visibleColumns = $state(
        Object.fromEntries(
            customerInvoiceColumns.map((column) => [column.key, true]),
        ),
    );

    let customer = $state(null);
    let invoices = $state([]);
    let loading = $state(true);
    let errorMessage = $state("");
    let statusFilter = $state("");

    let currentPage = $state(1);
    let lastPage = $state(1);
    let totalInvoices = $state(0);
    let invoiceSortDirection = $state("");
    let invoiceDateSortDirection = $state("desc");

    let isAdmin = $derived($user?.role === "admin");

    function getCustomerPhones(record) {
        const phones = record?.phone_numbers?.length
            ? record.phone_numbers
            : record?.phone
              ? [record.phone]
              : [];
        return phones.join(", ") || "No phone number";
    }

    const statusOptions = [
        { value: "", label: "   s" },
        { value: "paid", label: "Paid" },
        { value: "unpaid", label: "Unpaid" },
    ];

    async function loadCustomer(pageNumber = 1) {
        loading = true;
        errorMessage = "";

        try {
            const params = { page: pageNumber };

            if (statusFilter) {
                params.status = statusFilter;
            }
            if (invoiceSortDirection) {
                params.sort = "invoice_no";
                params.direction = invoiceSortDirection;
            } else if (invoiceDateSortDirection) {
                params.sort = "issued_date";
                params.direction = invoiceDateSortDirection;
            }

            const result = await api.getCustomer(page.params.id, params);

            customer = result.customer;
            invoices = result.invoices?.data ?? [];
            currentPage = result.invoices?.current_page ?? 1;
            lastPage = result.invoices?.last_page ?? 1;
            totalInvoices = result.invoices?.total ?? 0;
        } catch (error) {
            errorMessage = error?.message || "Unable to load customer";
        } finally {
            loading = false;
        }
    }

    function toggleSort(column) {
        if (column === "invoice_no") {
            invoiceSortDirection =
                invoiceSortDirection === "asc" ? "desc" : "asc";
            invoiceDateSortDirection = "";
        } else {
            invoiceDateSortDirection =
                invoiceDateSortDirection === "asc" ? "desc" : "asc";
            invoiceSortDirection = "";
        }
        loadCustomer(1);
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
            <p>{getCustomerPhones(customer)}</p>
        </div>
    </header>

    <section class="history-toolbar" aria-label="Invoice history filters">
        <div class="filter-field">
            <span class="filter-label">Payment Status</span>
            <FilterSelect
                bind:value={statusFilter}
                options={statusOptions}
                onchange={() => loadCustomer(1)}
                ariaLabel="Filter customer invoices by payment status"
                disabled={loading}
            />
        </div>

        <ColumnPicker
            columns={customerInvoiceColumns}
            bind:visible={visibleColumns}
        />
    </section>

    <section class="panel">
        {#if loading}
            <SkeletonTable rows={5} columns={5} />
        {:else if errorMessage}
            <div class="state error">{errorMessage}</div>
        {:else if invoices.length === 0}
            <div class="state">
                {statusFilter
                    ? `No ${statusFilter} invoices found.`
                    : "No invoice history found."}
            </div>
        {:else}
            <div class="table-card">
                <table class="data-table">
                    <thead>
                        <tr>
                            {#if visibleColumns.invoice}<th
                                    aria-sort={invoiceSortDirection === "asc"
                                        ? "ascending"
                                        : invoiceSortDirection === "desc"
                                          ? "descending"
                                          : "none"}
                                    ><button
                                        class="sort-header"
                                        type="button"
                                        onclick={() => toggleSort("invoice_no")}
                                        >Invoice Number <span aria-hidden="true"
                                            >{invoiceSortDirection === "asc"
                                                ? "↑"
                                                : invoiceSortDirection ===
                                                    "desc"
                                                  ? "↓"
                                                  : "↕"}</span
                                        ></button
                                    ></th
                                >{/if}
                            {#if visibleColumns.barrels}<th>Barrel Code</th
                                >{/if}
                            {#if visibleColumns.date}<th
                                    aria-sort={invoiceDateSortDirection ===
                                    "asc"
                                        ? "ascending"
                                        : invoiceDateSortDirection === "desc"
                                          ? "descending"
                                          : "none"}
                                    ><button
                                        class="sort-header"
                                        type="button"
                                        onclick={() =>
                                            toggleSort("issued_date")}
                                        >Invoice Date <span aria-hidden="true"
                                            >{invoiceDateSortDirection === "asc"
                                                ? "↑"
                                                : invoiceDateSortDirection ===
                                                    "desc"
                                                  ? "↓"
                                                  : "↕"}</span
                                        ></button
                                    ></th
                                >{/if}
                            {#if visibleColumns.status}<th>Status</th>{/if}
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        {#each invoices as invoice (invoice.id)}
                            <tr>
                                {#if visibleColumns.invoice}<td class="strong">
                                        <a
                                            class="invoice-link"
                                            href={`/invoices/${invoice.id}`}
                                            >{invoice.invoice_no}</a
                                        >
                                    </td>{/if}

                                {#if visibleColumns.barrels}<td>
                                        {invoice.items
                                            ?.map((item) => item.barrel?.code)
                                            .filter(Boolean)
                                            .join(", ") || "—"}
                                    </td>{/if}

                                {#if visibleColumns.date}<td>
                                        {formatDate(invoice.issued_date)}
                                    </td>{/if}

                                {#if visibleColumns.status}<td>
                                        <span
                                            class="status-badge"
                                            class:paid={invoice.status ===
                                                "paid"}
                                            class:unpaid={invoice.status ===
                                                "unpaid"}
                                        >
                                            {invoice.status === "paid"
                                                ? "Paid"
                                                : "Unpaid"}
                                        </span>
                                    </td>{/if}

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

<style>
    .history-toolbar {
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 16px;
        padding: 16px 20px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: white;
    }

    .filter-field {
        display: flex;
        flex-direction: column;
        gap: 7px;
        width: min(240px, 100%);
    }

    .filter-label {
        color: #1f2937;
        font-size: 13px;
        font-weight: 600;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 64px;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
    }

    .status-badge.paid {
        background: #ccfbf1;
        color: #0f766e;
    }

    .status-badge.unpaid {
        background: #fee2e2;
        color: #dc2626;
    }

    @media (max-width: 900px) {
        .history-toolbar {
            align-items: stretch;
            flex-direction: column;
        }

        .filter-field {
            width: 100%;
        }
    }
</style>
