import { createPortal } from 'react-dom';
import { formatDate } from '@/lib/format';

export type LetterIdentity = {
    foundation_name: string;
    app_name: string;
    address: string | null;
    logo_url: string | null;
    login_url: string;
};

export type GuardianCredentials = {
    name: string;
    username: string | null;
    password: string;
    relation: string;
    children: { name: string; classroom: string | null }[];
};

// Saat mencetak, hanya surat ini yang tampil; sisa halaman (termasuk dialog) disembunyikan.
const PRINT_CSS = `
@page { size: A5 portrait; margin: 12mm; }
@media print {
    body > *:not(#guardian-account-letter) { display: none !important; }
    #guardian-account-letter { display: block !important; }
    body { background: #fff !important; }
}
`;

/**
 * Surat akun wali untuk dicetak dari browser (tanpa PDF di server).
 * Kata sandi hanya ada di state halaman selama dialog kredensial terbuka;
 * mencetak ulang berarti reset kata sandi.
 */
export function GuardianAccountLetter({
    identity,
    credentials,
}: {
    identity: LetterIdentity;
    credentials: GuardianCredentials;
}) {
    return createPortal(
        <div
            id="guardian-account-letter"
            className="hidden bg-white font-sans text-[11pt] leading-relaxed text-black"
        >
            <style>{PRINT_CSS}</style>
            <header className="flex items-center gap-3 border-b-2 border-black pb-3">
                {identity.logo_url && (
                    <img
                        src={identity.logo_url}
                        alt=""
                        width={56}
                        height={56}
                        className="size-14 object-contain"
                    />
                )}
                <div>
                    <div className="text-[13pt] font-bold">
                        {identity.foundation_name}
                    </div>
                    <div>{identity.app_name}</div>
                    {identity.address && (
                        <div className="text-[9pt]">{identity.address}</div>
                    )}
                </div>
            </header>

            <h1 className="mt-5 text-center text-[13pt] font-bold uppercase">
                Surat Akun Wali Murid
            </h1>

            <p className="mt-4">
                Kepada Yth. {credentials.name} ({credentials.relation}),
            </p>
            <p className="mt-1">
                berikut akun untuk memantau{' '}
                {credentials.children.length > 1 ? 'anak-anak' : 'anak'}{' '}
                Bapak/Ibu:
            </p>
            <ul className="mt-1 list-disc pl-6">
                {credentials.children.map((child) => (
                    <li key={child.name}>
                        {child.name}
                        {child.classroom ? ` — ${child.classroom}` : ''}
                    </li>
                ))}
            </ul>

            <table className="mt-4 w-full border-collapse border border-black">
                <tbody>
                    <tr>
                        <th className="w-1/3 border border-black px-3 py-2 text-left font-normal">
                            Alamat aplikasi
                        </th>
                        <td className="border border-black px-3 py-2 font-mono break-all">
                            {identity.login_url}
                        </td>
                    </tr>
                    <tr>
                        <th className="border border-black px-3 py-2 text-left font-normal">
                            Username
                        </th>
                        <td className="border border-black px-3 py-2 font-mono text-[13pt]">
                            {credentials.username}
                        </td>
                    </tr>
                    <tr>
                        <th className="border border-black px-3 py-2 text-left font-normal">
                            Kata sandi
                        </th>
                        <td className="border border-black px-3 py-2 font-mono text-[13pt] tracking-wider">
                            {credentials.password}
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2 className="mt-4 font-bold">Cara masuk</h2>
            <ol className="mt-1 list-decimal space-y-0.5 pl-6">
                <li>Buka alamat aplikasi di atas lewat browser HP.</li>
                <li>Masukkan username (nomor HP) dan kata sandi di atas.</li>
                <li>
                    Setelah masuk, ganti kata sandi lewat menu Akun bila
                    diinginkan.
                </li>
                <li>
                    Jangan berikan kata sandi kepada siapa pun. Bila lupa, minta
                    reset kata sandi ke admin sekolah.
                </li>
            </ol>

            <p className="mt-6 text-right text-[9pt]">
                Dicetak {formatDate(new Date())}
            </p>
        </div>,
        document.body,
    );
}
