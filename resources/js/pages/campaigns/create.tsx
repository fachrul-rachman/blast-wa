import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import AppLayout from '@/layouts/app-layout';

type Template = {
    id: number;
    name: string;
    language_code: string;
    category: string | null;
    body_variables: string[];
    body_text: string | null;
};

type PageProps = {
    templates: Template[];
};

type CampaignForm = {
    title: string;
    whatsapp_template_id: string;
};

export default function CampaignsCreate({ templates }: PageProps) {
    const { data, setData, post, processing, errors } = useForm<CampaignForm>({
        title: '',
        whatsapp_template_id: '',
    });

    const selectedTemplate = templates.find(
        (template) => String(template.id) === data.whatsapp_template_id,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        post('/campaigns');
    }

    return (
        <>
            <Head title="Create Campaign" />
            <AppLayout
                title="Create Campaign"
                actions={
                    <Link
                        href="/campaigns"
                        className="inline-flex h-10 items-center rounded-md border border-zinc-300 px-3 text-sm font-medium hover:bg-zinc-100"
                    >
                        Back
                    </Link>
                }
            >
                <form
                    onSubmit={submit}
                    className="grid max-w-6xl gap-6 lg:grid-cols-[1fr_360px]"
                >
                    <section className="rounded-lg border border-zinc-200 bg-white p-5">
                        <div className="space-y-5">
                            <div>
                                <label
                                    htmlFor="title"
                                    className="block text-sm font-medium"
                                >
                                    Campaign Title
                                </label>
                                <input
                                    id="title"
                                    value={data.title}
                                    onChange={(event) =>
                                        setData('title', event.target.value)
                                    }
                                    className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                />
                                {errors.title && (
                                    <p className="mt-2 text-sm text-red-700">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="template"
                                    className="block text-sm font-medium"
                                >
                                    WhatsApp Template
                                </label>
                                <select
                                    id="template"
                                    value={data.whatsapp_template_id}
                                    onChange={(event) =>
                                        setData(
                                            'whatsapp_template_id',
                                            event.target.value,
                                        )
                                    }
                                    className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                >
                                    <option value="">Select template</option>
                                    {templates.map((template) => (
                                        <option
                                            key={template.id}
                                            value={template.id}
                                        >
                                            {template.name} (
                                            {template.language_code})
                                        </option>
                                    ))}
                                </select>
                                {errors.whatsapp_template_id && (
                                    <p className="mt-2 text-sm text-red-700">
                                        {errors.whatsapp_template_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Save Draft
                            </button>
                        </div>
                    </section>

                    <aside className="rounded-lg border border-zinc-200 bg-white p-5">
                        <h2 className="text-base font-semibold">
                            Template Snapshot
                        </h2>
                        {selectedTemplate ? (
                            <div className="mt-4 space-y-4 text-sm">
                                <div>
                                    <p className="text-zinc-500">Category</p>
                                    <p className="mt-1 font-medium">
                                        {selectedTemplate.category ?? '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-zinc-500">Variables</p>
                                    <p className="mt-1 font-medium">
                                        {selectedTemplate.body_variables
                                            .length || 0}
                                    </p>
                                </div>
                                <pre className="rounded-md bg-zinc-50 p-3 text-sm leading-6 whitespace-pre-wrap text-zinc-800">
                                    {selectedTemplate.body_text ?? '-'}
                                </pre>
                            </div>
                        ) : (
                            <p className="mt-4 text-sm text-zinc-600">
                                Select a template to preview its campaign
                                snapshot.
                            </p>
                        )}
                    </aside>
                </form>
            </AppLayout>
        </>
    );
}
