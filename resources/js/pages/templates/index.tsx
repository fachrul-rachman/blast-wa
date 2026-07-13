import { Head, Link, router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';

type Template = {
    id: number;
    name: string;
    language_code: string;
    category: string | null;
    status: string;
    body_variables: string[];
    is_supported: boolean;
    is_available: boolean;
    synced_at: string | null;
};

type PageProps = {
    templates: Template[];
    flash?: {
        status?: string;
    };
    errors?: {
        sync?: string;
    };
};

export default function TemplatesIndex({ templates }: PageProps) {
    const { props } = usePage<PageProps>();

    function syncTemplates() {
        router.post('/templates/sync');
    }

    return (
        <>
            <Head title="Templates" />
            <AppLayout
                title="Templates"
                actions={
                    <button
                        type="button"
                        onClick={syncTemplates}
                        className="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-700 px-3 text-sm font-medium text-white hover:bg-emerald-800"
                    >
                        <RefreshCw size={16} />
                        <span>Sync Templates</span>
                    </button>
                }
            >
                {props.flash?.status && (
                    <div className="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        {props.flash.status}
                    </div>
                )}

                {props.errors?.sync && (
                    <div className="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {props.errors.sync}
                    </div>
                )}

                <div className="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-zinc-200 bg-zinc-100 text-xs text-zinc-600 uppercase">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Language</th>
                                <th className="px-4 py-3">Category</th>
                                <th className="px-4 py-3">Variables</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Last Sync</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {templates.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-zinc-600"
                                    >
                                        No templates synced yet.
                                    </td>
                                </tr>
                            )}

                            {templates.map((template) => (
                                <tr
                                    key={template.id}
                                    className="border-b border-zinc-100 last:border-0"
                                >
                                    <td className="px-4 py-4 font-medium">
                                        {template.name}
                                    </td>
                                    <td className="px-4 py-4">
                                        {template.language_code}
                                    </td>
                                    <td className="px-4 py-4">
                                        {template.category ?? '-'}
                                    </td>
                                    <td className="px-4 py-4">
                                        {template.body_variables.length}
                                    </td>
                                    <td className="px-4 py-4">
                                        <span className="rounded-full border border-zinc-300 px-2 py-1 text-xs font-medium">
                                            {statusLabel(template)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-4 text-zinc-600">
                                        {template.synced_at
                                            ? new Date(
                                                  template.synced_at,
                                              ).toLocaleString()
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-4 text-right">
                                        <Link
                                            href={`/templates/${template.id}`}
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
            </AppLayout>
        </>
    );
}

function statusLabel(template: Template): string {
    if (template.is_available) {
        return 'Selectable';
    }

    if (!template.is_supported) {
        return 'Unsupported';
    }

    return template.status;
}
