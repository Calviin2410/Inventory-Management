const dateFormatter = new Intl.DateTimeFormat("en-MY", {
	day: "2-digit",
	month: "short",
	year: "numeric",
});

const currencyFormatter = new Intl.NumberFormat("en-MY", {
	style: "currency",
	currency: "MYR",
	minimumFractionDigits: 2,
});

export function formatDate(value, fallback = "—") {
	if (!value) return fallback;
	const date = new Date(String(value).includes("T") ? value : `${value}T00:00:00`);
	return Number.isNaN(date.getTime()) ? fallback : dateFormatter.format(date);
}

export function formatCurrency(value, fallback = "RM 0.00") {
	const amount = Number(value);
	return Number.isFinite(amount)
		? currencyFormatter.format(amount).replace("MYR", "RM")
		: fallback;
}
