import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type LoginForm = {
    username: string;
    password: string;
    remember: boolean;
};

export default function Login() {
    const { data, setData, post, processing, errors, reset } =
        useForm<LoginForm>({
            username: '',
            password: '',
            remember: false,
        });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        post('/login', {
            onFinish: () => reset('password'),
        });
    }

    return (
        <>
            <Head title="Login" />
            <main className="flex min-h-screen items-center justify-center bg-zinc-50 px-4 py-10 text-zinc-950">
                <section className="w-full max-w-sm">
                    <div className="mb-8">
                        <p className="text-sm font-medium text-emerald-700">
                            Blasting Message
                        </p>
                        <h1 className="mt-2 text-2xl font-semibold">
                            Admin Login
                        </h1>
                    </div>

                    <form
                        onSubmit={submit}
                        className="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm"
                    >
                        <div className="space-y-5">
                            <div>
                                <label
                                    htmlFor="username"
                                    className="block text-sm font-medium"
                                >
                                    Username
                                </label>
                                <input
                                    id="username"
                                    name="username"
                                    value={data.username}
                                    onChange={(event) =>
                                        setData('username', event.target.value)
                                    }
                                    autoComplete="username"
                                    className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                />
                                {errors.username && (
                                    <p className="mt-2 text-sm text-red-700">
                                        {errors.username}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label
                                    htmlFor="password"
                                    className="block text-sm font-medium"
                                >
                                    Password
                                </label>
                                <input
                                    id="password"
                                    name="password"
                                    type="password"
                                    value={data.password}
                                    onChange={(event) =>
                                        setData('password', event.target.value)
                                    }
                                    autoComplete="current-password"
                                    className="mt-2 block w-full rounded-md border border-zinc-300 px-3 py-2 text-sm outline-none focus:border-emerald-700 focus:ring-2 focus:ring-emerald-100"
                                />
                                {errors.password && (
                                    <p className="mt-2 text-sm text-red-700">
                                        {errors.password}
                                    </p>
                                )}
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={data.remember}
                                    onChange={(event) =>
                                        setData(
                                            'remember',
                                            event.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-zinc-300 text-emerald-700 focus:ring-emerald-700"
                                />
                                Remember me
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-md bg-emerald-700 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                Login
                            </button>
                        </div>
                    </form>
                </section>
            </main>
        </>
    );
}
