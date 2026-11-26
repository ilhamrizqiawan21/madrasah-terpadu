<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut berisi pesan galat bawaan yang digunakan oleh
    | kelas validator. Beberapa aturan memiliki beberapa versi seperti
    | aturan ukuran. Silakan sesuaikan setiap pesan di sini.
    |
    */

    'accepted' => 'Bidang :attribute harus diterima.',
    'accepted_if' => 'Bidang :attribute harus diterima ketika :other bernilai :value.',
    'active_url' => 'Bidang :attribute harus berupa URL yang valid.',
    'after' => 'Bidang :attribute harus berupa tanggal setelah :date.',
    'after_or_equal' => 'Bidang :attribute harus berupa tanggal setelah atau sama dengan :date.',
    'alpha' => 'Bidang :attribute hanya boleh berisi huruf.',
    'alpha_dash' => 'Bidang :attribute hanya boleh berisi huruf, angka, tanda hubung, dan garis bawah.',
    'alpha_num' => 'Bidang :attribute hanya boleh berisi huruf dan angka.',
    'any_of' => 'Bidang :attribute tidak valid.',
    'array' => 'Bidang :attribute harus berupa array.',
    'array_keys' => 'Bidang :attribute hanya boleh berisi kunci berikut: :values.',
    'ascii' => 'Bidang :attribute hanya boleh berisi karakter alfanumerik dan simbol satu-byte.',
    'base64' => 'Bidang :attribute harus berupa string Base64 yang valid.',
    'before' => 'Bidang :attribute harus berupa tanggal sebelum :date.',
    'before_or_equal' => 'Bidang :attribute harus berupa tanggal sebelum atau sama dengan :date.',
    'between' => [
        'array' => 'Bidang :attribute harus memiliki antara :min dan :max item.',
        'file' => 'Bidang :attribute harus berukuran antara :min dan :max kilobita.',
        'numeric' => 'Bidang :attribute harus bernilai antara :min dan :max.',
        'string' => 'Bidang :attribute harus berisi antara :min dan :max karakter.',
    ],
    'boolean' => 'Bidang :attribute harus bernilai benar atau salah.',
    'can' => 'Bidang :attribute mengandung nilai yang tidak diizinkan.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'contains' => 'Bidang :attribute tidak memiliki nilai yang diperlukan.',
    'current_password' => 'Kata sandi salah.',
    'date' => 'Bidang :attribute harus berupa tanggal yang valid.',
    'date_equals' => 'Bidang :attribute harus berupa tanggal yang sama dengan :date.',
    'date_format' => 'Bidang :attribute harus sesuai dengan format :format.',
    'decimal' => 'Bidang :attribute harus memiliki :decimal angka di belakang koma.',
    'declined' => 'Bidang :attribute harus ditolak.',
    'declined_if' => 'Bidang :attribute harus ditolak ketika :other bernilai :value.',
    'different' => 'Bidang :attribute dan :other harus berbeda.',
    'digits' => 'Bidang :attribute harus terdiri dari :digits digit.',
    'digits_between' => 'Bidang :attribute harus terdiri dari :min sampai :max digit.',
    'dimensions' => 'Dimensi gambar :attribute tidak valid.',
    'distinct' => 'Bidang :attribute memiliki nilai yang duplikat.',
    'doesnt_contain' => 'Bidang :attribute tidak boleh mengandung salah satu dari: :values.',
    'doesnt_end_with' => 'Bidang :attribute tidak boleh diakhiri dengan salah satu dari: :values.',
    'doesnt_start_with' => 'Bidang :attribute tidak boleh diawali dengan salah satu dari: :values.',
    'email' => 'Bidang :attribute harus berupa alamat email yang valid.',
    'encoding' => 'Bidang :attribute harus dikodekan dalam :encoding.',
    'ends_with' => 'Bidang :attribute harus diakhiri dengan salah satu dari: :values.',
    'enum' => ':attribute yang dipilih tidak valid.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'extensions' => 'Bidang :attribute harus memiliki salah satu ekstensi berikut: :values.',
    'file' => 'Bidang :attribute harus berupa berkas.',
    'filled' => 'Bidang :attribute harus memiliki nilai.',
    'gt' => [
        'array' => 'Bidang :attribute harus memiliki lebih dari :value item.',
        'file' => 'Bidang :attribute harus lebih besar dari :value kilobita.',
        'numeric' => 'Bidang :attribute harus lebih besar dari :value.',
        'string' => 'Bidang :attribute harus lebih dari :value karakter.',
    ],
    'gte' => [
        'array' => 'Bidang :attribute harus memiliki :value item atau lebih.',
        'file' => 'Bidang :attribute harus lebih besar atau sama dengan :value kilobita.',
        'numeric' => 'Bidang :attribute harus lebih besar atau sama dengan :value.',
        'string' => 'Bidang :attribute harus minimal :value karakter.',
    ],
    'hex_color' => 'Bidang :attribute harus berupa warna heksadesimal yang valid.',
    'image' => 'Bidang :attribute harus berupa gambar.',
    'in' => ':attribute yang dipilih tidak valid.',
    'in_array' => 'Bidang :attribute harus ada di dalam :other.',
    'in_array_keys' => 'Bidang :attribute harus mengandung setidaknya salah satu kunci berikut: :values.',
    'integer' => 'Bidang :attribute harus berupa bilangan bulat.',
    'ip' => 'Bidang :attribute harus berupa alamat IP yang valid.',
    'ipv4' => 'Bidang :attribute harus berupa alamat IPv4 yang valid.',
    'ipv6' => 'Bidang :attribute harus berupa alamat IPv6 yang valid.',
    'json' => 'Bidang :attribute harus berupa string JSON yang valid.',
    'list' => 'Bidang :attribute harus berupa daftar.',
    'lowercase' => 'Bidang :attribute harus berupa huruf kecil.',
    'lt' => [
        'array' => 'Bidang :attribute harus memiliki kurang dari :value item.',
        'file' => 'Bidang :attribute harus kurang dari :value kilobita.',
        'numeric' => 'Bidang :attribute harus kurang dari :value.',
        'string' => 'Bidang :attribute harus kurang dari :value karakter.',
    ],
    'lte' => [
        'array' => 'Bidang :attribute tidak boleh memiliki lebih dari :value item.',
        'file' => 'Bidang :attribute harus kurang atau sama dengan :value kilobita.',
        'numeric' => 'Bidang :attribute harus kurang atau sama dengan :value.',
        'string' => 'Bidang :attribute maksimal :value karakter.',
    ],
    'mac_address' => 'Bidang :attribute harus berupa alamat MAC yang valid.',
    'max' => [
        'array' => 'Bidang :attribute tidak boleh memiliki lebih dari :max item.',
        'file' => 'Bidang :attribute tidak boleh lebih besar dari :max kilobita.',
        'numeric' => 'Bidang :attribute tidak boleh lebih besar dari :max.',
        'string' => 'Bidang :attribute tidak boleh lebih dari :max karakter.',
    ],
    'max_digits' => 'Bidang :attribute tidak boleh lebih dari :max digit.',
    'mimes' => 'Bidang :attribute harus berupa berkas bertipe: :values.',
    'mimetypes' => 'Bidang :attribute harus berupa berkas bertipe: :values.',
    'min' => [
        'array' => 'Bidang :attribute minimal harus memiliki :min item.',
        'file' => 'Bidang :attribute minimal harus berukuran :min kilobita.',
        'numeric' => 'Bidang :attribute minimal harus bernilai :min.',
        'string' => 'Bidang :attribute minimal harus berisi :min karakter.',
    ],
    'min_digits' => 'Bidang :attribute minimal harus terdiri dari :min digit.',
    'missing' => 'Bidang :attribute harus tidak ada.',
    'missing_if' => 'Bidang :attribute harus tidak ada ketika :other bernilai :value.',
    'missing_unless' => 'Bidang :attribute harus tidak ada kecuali :other bernilai :value.',
    'missing_with' => 'Bidang :attribute harus tidak ada ketika :values ada.',
    'missing_with_all' => 'Bidang :attribute harus tidak ada ketika :values ada.',
    'multiple_of' => 'Bidang :attribute harus merupakan kelipatan dari :value.',
    'not_in' => ':attribute yang dipilih tidak valid.',
    'not_regex' => 'Format bidang :attribute tidak valid.',
    'numeric' => 'Bidang :attribute harus berupa angka.',
    'password' => [
        'letters' => 'Bidang :attribute harus mengandung setidaknya satu huruf.',
        'mixed' => 'Bidang :attribute harus mengandung setidaknya satu huruf besar dan satu huruf kecil.',
        'numbers' => 'Bidang :attribute harus mengandung setidaknya satu angka.',
        'symbols' => 'Bidang :attribute harus mengandung setidaknya satu simbol.',
        'uncompromised' => ':attribute yang diberikan pernah muncul dalam kebocoran data. Silakan pilih :attribute lain.',
    ],
    'present' => 'Bidang :attribute harus ada.',
    'present_if' => 'Bidang :attribute harus ada ketika :other bernilai :value.',
    'present_unless' => 'Bidang :attribute harus ada kecuali :other bernilai :value.',
    'present_with' => 'Bidang :attribute harus ada ketika :values ada.',
    'present_with_all' => 'Bidang :attribute harus ada ketika :values ada.',
    'prohibited' => 'Bidang :attribute dilarang.',
    'prohibited_if' => 'Bidang :attribute dilarang ketika :other bernilai :value.',
    'prohibited_if_accepted' => 'Bidang :attribute dilarang ketika :other diterima.',
    'prohibited_if_declined' => 'Bidang :attribute dilarang ketika :other ditolak.',
    'prohibited_unless' => 'Bidang :attribute dilarang kecuali :other ada dalam :values.',
    'prohibits' => 'Bidang :attribute melarang :other untuk ada.',
    'regex' => 'Format bidang :attribute tidak valid.',
    'required' => 'Bidang :attribute wajib diisi.',
    'required_array_keys' => 'Bidang :attribute harus memiliki entri untuk: :values.',
    'required_if' => 'Bidang :attribute wajib diisi ketika :other bernilai :value.',
    'required_if_accepted' => 'Bidang :attribute wajib diisi ketika :other diterima.',
    'required_if_declined' => 'Bidang :attribute wajib diisi ketika :other ditolak.',
    'required_unless' => 'Bidang :attribute wajib diisi kecuali :other ada dalam :values.',
    'required_with' => 'Bidang :attribute wajib diisi ketika :values ada.',
    'required_with_all' => 'Bidang :attribute wajib diisi ketika :values ada.',
    'required_without' => 'Bidang :attribute wajib diisi ketika :values tidak ada.',
    'required_without_all' => 'Bidang :attribute wajib diisi ketika tidak ada satu pun dari :values yang ada.',
    'same' => 'Bidang :attribute harus sama dengan :other.',
    'size' => [
        'array' => 'Bidang :attribute harus berisi :size item.',
        'file' => 'Bidang :attribute harus berukuran :size kilobita.',
        'numeric' => 'Bidang :attribute harus bernilai :size.',
        'string' => 'Bidang :attribute harus berisi :size karakter.',
    ],
    'starts_with' => 'Bidang :attribute harus diawali dengan salah satu dari: :values.',
    'string' => 'Bidang :attribute harus berupa teks.',
    'timezone' => 'Bidang :attribute harus berupa zona waktu yang valid.',
    'unique' => ':attribute sudah digunakan.',
    'uploaded' => ':attribute gagal diunggah.',
    'uppercase' => 'Bidang :attribute harus berupa huruf kapital.',
    'url' => 'Bidang :attribute harus berupa URL yang valid.',
    'ulid' => 'Bidang :attribute harus berupa ULID yang valid.',
    'uuid' => 'Bidang :attribute harus berupa UUID yang valid.',

    /*
    |--------------------------------------------------------------------------
    | Baris Bahasa Validasi Kustom
    |--------------------------------------------------------------------------
    |
    | Di sini Anda dapat menentukan pesan validasi kustom untuk atribut
    | menggunakan konvensi "atribut.aturan" untuk menamai baris.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'pesan-kustom',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Atribut Validasi Kustom
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut digunakan untuk mengganti placeholder atribut dengan
    | istilah yang lebih mudah dibaca pengguna, misalnya "alamat email" alih-alih
    | "email".
    |
    */

    'attributes' => [
        'academic_year_id' => 'tahun ajaran',
        'address' => 'alamat',
        'amount' => 'nominal',
        'birth_date' => 'tanggal lahir',
        'birth_place' => 'tempat lahir',
        'classroom_id' => 'rombel',
        'current_password' => 'kata sandi saat ini',
        'date' => 'tanggal',
        'description' => 'deskripsi',
        'email' => 'email',
        'file' => 'berkas',
        'gender' => 'jenis kelamin',
        'guardian_id' => 'wali',
        'name' => 'nama',
        'nickname' => 'nama panggilan',
        'nis' => 'NIS',
        'nisn' => 'NISN',
        'notes' => 'catatan',
        'password' => 'kata sandi',
        'password_confirmation' => 'konfirmasi kata sandi',
        'phone' => 'nomor HP',
        'photo' => 'foto',
        'reason' => 'alasan',
        'status' => 'status',
        'student_id' => 'siswa',
        'title' => 'judul',
        'unit_id' => 'unit',
        'username' => 'username',
    ],

];
