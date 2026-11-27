export type RoleOption = {
    value: string;
    label: string;
};

export type StaffProfile = {
    unit_id: number | null;
    unit_code: string | null;
    nip: string | null;
    position: string | null;
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
