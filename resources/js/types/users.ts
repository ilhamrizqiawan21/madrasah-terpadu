export type RoleOption = {
    value: string;
    label: string;
};

export type StaffProfile = {
    unit_id: number | null;
    unit_code: string | null;
    nip: string | null;
    position: string | null;
    title_prefix: string | null;
    title_suffix: string | null;
    nik: string | null;
    nuptk: string | null;
    gender: 'L' | 'P' | null;
    birth_place: string | null;
    birth_date: string | null;
    address: string | null;
    assignment_type: string | null;
    assignment_label: string | null;
    employment_status: string | null;
    employment_label: string | null;
    base_salary: number;
    allowance: number;
    hourly_rate: number;
    photo_url: string | null;
};

export type StaffUser = {
    id: number;
    name: string;
    username: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
    roles: RoleOption[];
    profile?: StaffProfile | null;
    homeroom_classrooms?: string[];
    two_factor_enabled: boolean;
    last_login_at: string | null;
    is_self: boolean;
};

export type IssuedCredentials = {
    name: string;
    username: string | null;
    password: string;
};

export type PaginatedResource<T> = {
    data: T[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
};
