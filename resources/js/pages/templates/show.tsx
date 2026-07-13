import { Head, Link } from '@inertiajs/react';
import { Image, MessageSquareText } from 'lucide-react';
import type { ReactNode } from 'react';

import AppLayout from '@/layouts/app-layout';

type TemplateComponent = {
    type?: string;
    format?: string;
    text?: string;
    example?: {
        header_handle?: string[];
    };
};

type Template = {
    id: number;
    name: string;
    language_code: string;
    category: string | null;
    status: string;
    body_text: string | null;
    body_variables: string[];
    components: TemplateComponent[];
    is_supported: boolean;
    is_available: boolean;
    synced_at: string | null;
};

type PageProps = {
    template: Template;
};

export default function TemplateShow({ template }: PageProps) {
    const header = template.components.find(
        (component) => component.type?.toUpperCase() === 'HEADER',
    );

    return (
        <>
            <Head title={template.name} />
            <AppLayout
                title={template.name}
                actions={
                    <Link
                        href="/templates"
                        className="inline-flex h-10 items-center rounded-md border border-zinc-300 px-3 text-sm font-medium hover:bg-zinc-100"
                    >
                        Back
                    </Link>
                }
            >
                <div className="grid max-w-6xl gap-6 lg:grid-cols-[1fr_320px]">
                    <div className="space-y-6">
                        <section className="rounded-lg border border-zinc-200 bg-white p-5">
                            <div className="mb-4 flex items-center gap-2">
                                <MessageSquareText
                                    size={18}
                                    className="text-emerald-700"
                                />
                                <h2 className="text-base font-semibold">
                                    Body Preview
                                </h2>
                            </div>
                            <pre className="rounded-md bg-zinc-50 p-4 text-sm leading-6 whitespace-pre-wrap text-zinc-800">
                                {template.body_text ?? '-'}
                            </pre>
                        </section>

                        <section className="rounded-lg border border-zinc-200 bg-white p-5">
                            <div className="mb-4 flex items-center gap-2">
                                <Image size={18} className="text-emerald-700" />
                                <h2 className="text-base font-semibold">
                                    Header
                                </h2>
                            </div>
                            {header ? (
                                <HeaderPreview component={header} />
                            ) : (
                                <p className="text-sm text-zinc-600">
                                    No header component.
                                </p>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-6">
                        <section className="rounded-lg border border-zinc-200 bg-white p-5">
                            <h2 className="mb-4 text-base font-semibold">
                                Metadata
                            </h2>
                            <dl className="space-y-3 text-sm">
                                <Info label="Language">
                                    {template.language_code}
                                </Info>
                                <Info label="Category">
                                    {template.category ?? '-'}
                                </Info>
                                <Info label="Status">
                                    {statusLabel(template)}
                                </Info>
                                <Info label="Last Sync">
                                    {template.synced_at
                                        ? new Date(
                                              template.synced_at,
                                          ).toLocaleString()
                                        : '-'}
                                </Info>
                            </dl>
                        </section>

                        <section className="rounded-lg border border-zinc-200 bg-white p-5">
                            <h2 className="mb-4 text-base font-semibold">
                                Variables
                            </h2>
                            {template.body_variables.length > 0 ? (
                                <div className="flex flex-wrap gap-2">
                                    {template.body_variables.map((variable) => (
                                        <span
                                            key={variable}
                                            className="rounded-full border border-zinc-300 bg-zinc-50 px-2 py-1 text-xs font-medium"
                                        >
                                            {`{{${variable}}}`}
                                        </span>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-zinc-600">
                                    No body variables.
                                </p>
                            )}
                        </section>
                    </aside>
                </div>
            </AppLayout>
        </>
    );
}

function HeaderPreview({ component }: { component: TemplateComponent }) {
    const format = component.format?.toUpperCase() ?? 'TEXT';
    const exampleImage = component.example?.header_handle?.[0];

    if (format === 'IMAGE') {
        return (
            <div className="rounded-md border border-dashed border-zinc-300 bg-zinc-50 p-5">
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-md bg-white text-emerald-700">
                        <Image size={22} />
                    </div>
                    <div>
                        <p className="text-sm font-medium">
                            Image header template
                        </p>
                        <p className="mt-1 text-sm text-zinc-600">
                            Meta sync exposes the image header format. The
                            actual media file is supplied later during message
                            delivery.
                        </p>
                    </div>
                </div>
                {exampleImage && (
                    <p className="mt-4 text-xs break-all text-zinc-500">
                        Example handle: {exampleImage}
                    </p>
                )}
            </div>
        );
    }

    return (
        <pre className="rounded-md bg-zinc-50 p-4 text-sm leading-6 whitespace-pre-wrap text-zinc-800">
            {component.text ?? `Header format: ${format}`}
        </pre>
    );
}

function Info({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div>
            <dt className="text-zinc-500">{label}</dt>
            <dd className="mt-1 font-medium">{children}</dd>
        </div>
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
