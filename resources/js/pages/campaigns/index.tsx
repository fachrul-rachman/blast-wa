import { Head, Link, router } from '@inertiajs/react';
import { Filter, PlusCircle, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import AppLayout from '@/layouts/app-layout';

type Campaign = {
    id: number;
    title: string;
    status: string;
    template_name: string;
    created_at: string | null;
};
type Template = {
    id: number;
    name: string;
};
type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Filters = {
    search: string;
    status: string;
    template_id: string;
    date_from: string;
    date_to: string;
};
type PageProps = {
    campaigns: Paginator<Campaign>;
    filters: Filters;
    templates: Template[];
};

const statuses = [
    'draft',
    'scheduled',
    'processing',
    'completed',
    'cancelled',
    'failed',
];

export default function CampaignsIndex({
    campaigns,
    filters,
    templates,
}: PageProps) {
    const [values, setValues] = useState(filters);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/campaigns', compact(values), {
            preserveState: true,
            replace: true,
        });
    }

    function reset() {
        const empty = {
            search: '',
            status: '',
            template_id: '',
            date_from: '',
            date_to: '',
        };

        setValues(empty);
        router.get('/campaigns', {}, { replace: true });
    }

    return (
        <>
            <Head title="Campaigns" />
            <AppLayout
                title="Campaigns"
                actions={
                    <Link
                        href="/campaigns/create"
                        className="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                    >
                        <PlusCircle size={16} />
                        <span>Create Campaign</span>
                    </Link>
                }
            >
                <form
                    onSubmit={submit}
                    className="mb-5 grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 lg:grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto_auto]"
                >
                    <label className="block text-sm font-medium">
                        Search
                        <input
                            value={values.search}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    search: event.target.value,
                                })
                            }
                            className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                        />
                    </label>
                    <label className="block text-sm font-medium">
                        Status
                        <select
                            value={values.status}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    status: event.target.value,
                                })
                            }
                            className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                        >
                            <option value="">All</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="block text-sm font-medium">
                        Template
                        <select
                            value={values.template_id}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    template_id: event.target.value,
                                })
                            }
                            className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
                        >
                            <option value="">All</option>
                            {templates.map((template) => (
                                <option key={template.id} value={template.id}>
                                    {template.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <DateInput
                        label="From"
                        value={values.date_from}
                        onChange={(date_from) =>
                            setValues({ ...values, date_from })
                        }
                    />
                    <DateInput
                        label="To"
                        value={values.date_to}
                        onChange={(date_to) => setValues({ ...values, date_to })}
                    />
                    <button
                        type="submit"
                        className="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                    >
                        <Search size={16} />
                        <span>Apply</span>
                    </button>
                    <button
                        type="button"
                        onClick={reset}
                        className="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md border border-zinc-300 px-3 text-sm font-medium hover:bg-zinc-100"
                    >
                        <Filter size={16} />
                        <span>Reset</span>
                    </button>
                </form>

                <div className="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-zinc-200 bg-zinc-100 text-xs text-zinc-600 uppercase">
                            <tr>
                                <th className="px-4 py-3">Title</th>
                                <th className="px-4 py-3">Template</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Created</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {campaigns.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-10 text-center text-zinc-600"
                                    >
                                        No campaigns found.
                                    </td>
                                </tr>
                            )}

                            {campaigns.data.map((campaign) => (
                                <tr
                                    key={campaign.id}
                                    className="border-b border-zinc-100 last:border-0"
                                >
                                    <td className="px-4 py-4 font-medium">
                                        {campaign.title}
                                    </td>
                                    <td className="px-4 py-4">
                                        {campaign.template_name}
                                    </td>
                                    <td className="px-4 py-4">
                                        <span className="rounded-full border border-zinc-300 px-2 py-1 text-xs font-medium capitalize">
                                            {campaign.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-4 text-zinc-600">
                                        {campaign.created_at
                                            ? new Date(
                                                  campaign.created_at,
                                              ).toLocaleString()
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-4 text-right">
                                        <Link
                                            href={`/campaigns/${campaign.id}`}
                                            className="text-sm font-medium text-emerald-700 hover:text-emerald-900"
                                        >
                                            Detail
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination paginator={campaigns} />
            </AppLayout>
        </>
    );
}

function DateInput({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <label className="block text-sm font-medium">
            {label}
            <input
                type="date"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm"
            />
        </label>
    );
}

function Pagination({ paginator }: { paginator: Paginator<unknown> }) {
    if (paginator.last_page <= 1) {
        return null;
    }

    return (
        <div className="mt-4 flex items-center justify-between text-sm">
            <button
                type="button"
                disabled={!paginator.prev_page_url}
                onClick={() =>
                    paginator.prev_page_url &&
                    router.get(paginator.prev_page_url, {}, { preserveState: true })
                }
                className="rounded-md border border-zinc-300 px-3 py-2 font-medium hover:bg-zinc-100 disabled:opacity-50"
            >
                Previous
            </button>
            <span className="text-zinc-600">
                Page {paginator.current_page} of {paginator.last_page}
            </span>
            <button
                type="button"
                disabled={!paginator.next_page_url}
                onClick={() =>
                    paginator.next_page_url &&
                    router.get(paginator.next_page_url, {}, { preserveState: true })
                }
                className="rounded-md border border-zinc-300 px-3 py-2 font-medium hover:bg-zinc-100 disabled:opacity-50"
            >
                Next
            </button>
        </div>
    );
}

function compact(values: Filters): Partial<Filters> {
    return Object.fromEntries(
        Object.entries(values).filter(([, value]) => value !== ''),
    ) as Partial<Filters>;
}
