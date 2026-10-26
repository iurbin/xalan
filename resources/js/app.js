import './bootstrap';
import { Modal, Toast } from 'bootstrap';

const tokenKey = 'aquaops_api_token';
const currentUserKey = 'aquaops_user';
const apiBase = '/api';
let customerRecords = [];
let propertyRecords = [];
let visitRecords = [];
let planRecords = [];
let teamRecords = [];
let currentUser = null;
let editingVisitId = null;

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
}[character]));

function readToken() {
    return window.sessionStorage.getItem(tokenKey);
}

function setSession(token, user) {
    window.sessionStorage.setItem(tokenKey, token);
    window.sessionStorage.setItem(currentUserKey, JSON.stringify(user));
}

function clearSession() {
    window.sessionStorage.removeItem(tokenKey);
    window.sessionStorage.removeItem(currentUserKey);
}

async function api(path, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('Accept', 'application/json');

    if (readToken()) {
        headers.set('Authorization', `Bearer ${readToken()}`);
    }

    if (options.body && !(options.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
        options.body = JSON.stringify(options.body);
    }

    const response = await fetch(`${apiBase}${path}`, { ...options, headers });
    if (response.status === 401) {
        clearSession();
        window.location.assign('/login');
        throw new Error('Your session has expired. Please sign in again.');
    }

    if (response.status === 204) return null;

    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
        const messages = result.errors ? Object.values(result.errors).flat().join(' ') : result.message;
        throw new Error(messages || `Request failed (${response.status}).`);
    }

    return result;
}

function toast(message) {
    const messageTarget = document.getElementById('app-toast-message');
    const toastTarget = document.getElementById('app-toast');
    if (!messageTarget || !toastTarget) return;
    messageTarget.textContent = message;
    Toast.getOrCreateInstance(toastTarget, { delay: 3500 }).show();
}

function showError(error) {
    toast(error.message || 'Something went wrong. Please try again.');
}

function statusBadge(status) {
    const value = String(status || 'unknown');
    return `<span class="badge-status status-${escapeHtml(value)}">${escapeHtml(value.replaceAll('_', ' '))}</span>`;
}

function displayDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    return Number.isNaN(date.valueOf()) ? '—' : date.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
}

function dateTimeLocalValue(value) {
    const date = new Date(value);
    if (Number.isNaN(date.valueOf())) return '';
    date.setMinutes(date.getMinutes() - date.getTimezoneOffset());
    return date.toISOString().slice(0, 16);
}

function displayMoney(value, currency = 'USD') {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '—';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amount);
}

function intervalLabel(interval, count) {
    const plural = Number(count) !== 1;
    const label = { day: 'day', week: 'week', month: 'month', year: 'year' }[interval] || interval;
    return `${count} ${label}${plural ? 's' : ''}`;
}

function paginationData(response) {
    return Array.isArray(response) ? response : (response.data || []);
}

async function loadCurrentUser() {
    try {
        currentUser = JSON.parse(window.sessionStorage.getItem(currentUserKey) || 'null');
    } catch {
        currentUser = null;
    }

    const response = await api('/user');
    currentUser = response;
    window.sessionStorage.setItem(currentUserKey, JSON.stringify(response));
    const name = document.getElementById('user-name');
    const avatar = document.getElementById('user-avatar');
    if (name) name.textContent = response.name || response.email;
    if (avatar) avatar.textContent = (response.name || response.email || 'A').trim().charAt(0).toUpperCase();
    const teamLink = document.getElementById('team-nav-link');
    const isAdmin = response.role === 'admin';
    if (!isAdmin) {
        [teamLink, document.getElementById('customers-nav-link'), document.getElementById('properties-nav-link'), document.getElementById('billing-nav-link')]
            .filter(Boolean)
            .forEach((link) => link.classList.add('d-none'));
    }
    const scheduleButton = document.getElementById('schedule-visit-button');
    if (scheduleButton && !isAdmin) scheduleButton.classList.add('d-none');
}

function bindLogout() {
    document.querySelectorAll('[data-action="logout"]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await api('/logout', { method: 'POST' });
            } catch {
                // The local token is cleared even if it has already expired.
            }
            clearSession();
            window.location.assign('/login');
        });
    });
}

function formObject(form) {
    return Object.fromEntries(new FormData(form).entries());
}

function modal(id) {
    return Modal.getOrCreateInstance(document.getElementById(id));
}

async function initializeLogin() {
    if (readToken()) {
        window.location.assign('/app');
        return;
    }

    let registering = false;
    const form = document.getElementById('auth-form');
    const errorBox = document.getElementById('auth-error');
    const submit = document.getElementById('auth-submit');

    document.getElementById('auth-mode-toggle').addEventListener('click', (event) => {
        registering = !registering;
        document.getElementById('tenant-name-field').classList.toggle('d-none', !registering);
        document.getElementById('name-field').classList.toggle('d-none', !registering);
        document.getElementById('password-confirmation-field').classList.toggle('d-none', !registering);
        document.getElementById('auth-heading').textContent = registering ? 'Create your workspace' : 'Welcome back';
        document.getElementById('auth-copy').textContent = registering
            ? 'Set up your company and administrator account.'
            : 'Sign in to manage your field operations.';
        document.getElementById('auth-switch-copy').textContent = registering ? 'Already have an account?' : 'New to ForceOps?';
        event.currentTarget.textContent = registering ? 'Sign in' : 'Create an account';
        submit.textContent = registering ? 'Create workspace' : 'Sign in';
        document.getElementById('password').autocomplete = registering ? 'new-password' : 'current-password';
        errorBox.classList.add('d-none');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        errorBox.classList.add('d-none');
        submit.disabled = true;
        const payload = formObject(form);

        try {
            const response = await api(registering ? '/register' : '/login', {
                method: 'POST',
                body: payload,
            });
            setSession(response.token, response.user);
            window.location.assign('/app');
        } catch (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('d-none');
        } finally {
            submit.disabled = false;
        }
    });
}

function setTableMessage(target, message, colspan) {
    target.innerHTML = `<tr><td colspan="${colspan}" class="empty-state">${escapeHtml(message)}</td></tr>`;
}

async function loadCustomers() {
    const response = await api('/customers');
    customerRecords = paginationData(response);
    const target = document.getElementById('customers-table');
    if (!customerRecords.length) {
        setTableMessage(target, 'No customers yet. Add your first customer to get started.', 6);
        return;
    }
    target.innerHTML = customerRecords.map((customer) => `
        <tr>
            <td><span class="table-primary-text">${escapeHtml(customer.name)}</span></td>
            <td>${escapeHtml(customer.email || '—')}</td>
            <td>${escapeHtml(customer.phone || '—')}</td>
            <td>${escapeHtml(customer.properties_count ?? customer.properties?.length ?? 0)}</td>
            <td>${statusBadge(customer.status)}</td>
            <td><div class="row-actions"><button class="btn-table" data-action="edit-customer" data-id="${customer.id}">Edit</button><button class="btn-table danger" data-action="delete-customer" data-id="${customer.id}">Delete</button></div></td>
        </tr>`).join('');
}

function fillCustomerForm(customer = null) {
    const form = document.getElementById('customer-form');
    form.reset();
    form.elements.id.value = customer?.id || '';
    form.elements.name.value = customer?.name || '';
    form.elements.email.value = customer?.email || '';
    form.elements.phone.value = customer?.phone || '';
    form.elements.status.value = customer?.status || 'active';
    document.getElementById('customer-modal-title').textContent = customer ? 'Edit customer' : 'Add customer';
    modal('customer-modal').show();
}

async function loadProperties() {
    const [propertiesResponse, customersResponse] = await Promise.all([api('/properties'), api('/customers')]);
    propertyRecords = paginationData(propertiesResponse);
    customerRecords = paginationData(customersResponse);
    const target = document.getElementById('properties-table');
    if (!propertyRecords.length) {
        setTableMessage(target, 'No properties yet. Add a service location to get started.', 5);
    } else {
        target.innerHTML = propertyRecords.map((property) => `
            <tr>
                <td><span class="table-primary-text">${escapeHtml(property.name || property.address_line_1)}</span><div class="table-secondary-text">${escapeHtml(property.address_line_1)}</div></td>
                <td>${escapeHtml(property.customer?.name || '—')}</td>
                <td>${escapeHtml(property.pool_type || '—')}</td>
                <td>${escapeHtml([property.city, property.state].filter(Boolean).join(', '))}</td>
                <td><div class="row-actions"><button class="btn-table" data-action="edit-property" data-id="${property.id}">Edit</button><button class="btn-table danger" data-action="delete-property" data-id="${property.id}">Delete</button></div></td>
            </tr>`).join('');
    }

    const customerSelect = document.getElementById('property-customer');
    customerSelect.innerHTML = '<option value="">Choose a customer</option>' + customerRecords
        .map((customer) => `<option value="${customer.id}">${escapeHtml(customer.name)}</option>`).join('');
}

function fillPropertyForm(property = null) {
    const form = document.getElementById('property-form');
    form.reset();
    form.elements.id.value = property?.id || '';
    form.elements.customer_id.value = property?.customer_id || '';
    form.elements.name.value = property?.name || '';
    form.elements.address_line_1.value = property?.address_line_1 || '';
    form.elements.address_line_2.value = property?.address_line_2 || '';
    form.elements.city.value = property?.city || '';
    form.elements.state.value = property?.state || '';
    form.elements.postal_code.value = property?.postal_code || '';
    form.elements.country.value = property?.country || 'US';
    form.elements.pool_type.value = property?.pool_type || '';
    form.elements.access_code.value = '';
    document.getElementById('property-modal-title').textContent = property ? 'Edit property' : 'Add property';
    modal('property-modal').show();
}

async function loadVisits() {
    const status = document.getElementById('visit-status-filter')?.value;
    const response = await api(`/visits${status ? `?status=${encodeURIComponent(status)}` : ''}`);
    visitRecords = paginationData(response);
    const target = document.getElementById('visits-table');
    if (!visitRecords.length) {
        setTableMessage(target, 'No visits match this filter.', 5);
        return;
    }
    target.innerHTML = visitRecords.map((visit) => {
        const property = visit.property || {};
        const customer = property.customer || {};
        let actions = '';
        if (visit.status === 'scheduled' && (
            currentUser?.role === 'admin'
            || Number(visit.assigned_to) === Number(currentUser?.id)
        )) {
            actions += `<button class="btn-table" data-action="start-visit" data-id="${visit.id}">Start</button>`;
        }
        if (visit.status === 'in_progress' && (
            currentUser?.role === 'admin'
            || Number(visit.assigned_to) === Number(currentUser?.id)
        )) {
            actions += `<button class="btn-table" data-action="complete-visit" data-id="${visit.id}">Complete</button>`;
        }
        if (visit.status === 'scheduled' && currentUser?.role === 'admin') {
            actions += `<button class="btn-table" data-action="edit-visit" data-id="${visit.id}">Edit</button>`;
            actions += `<button class="btn-table danger" data-action="delete-visit" data-id="${visit.id}">Delete</button>`;
        }
        if (visit.status === 'completed' && visit.report_path) {
            actions += `<a class="btn-table" href="/api/visits/${visit.id}/report" data-action="download-report" data-id="${visit.id}">Report</a>`;
        }
        if (visit.status === 'completed' && currentUser?.role === 'admin') {
            actions += visit.invoice
                ? (visit.invoice.deleted_at
                    ? '<span class="muted">Invoice archived</span>'
                    : `<button class="btn-table" data-action="download-visit-invoice" data-id="${visit.invoice.id}">Invoice PDF</button>`)
                : `<button class="btn-table" data-action="create-visit-invoice" data-id="${visit.id}">Create invoice</button>`;
        }
        actions += `<button class="btn-table" data-action="visit-details" data-id="${visit.id}">Details</button>`;
        return `<tr>
            <td><span class="table-primary-text">${escapeHtml(customer.name || 'Customer')}</span><div class="table-secondary-text">${escapeHtml(property.name || property.address_line_1 || 'Property')}</div></td>
            <td>${escapeHtml(visit.technician?.name || 'Unassigned')}</td>
            <td>${escapeHtml(displayDate(visit.scheduled_at))}</td>
            <td>${statusBadge(visit.status)}</td>
            <td><div class="row-actions">${actions}</div></td>
        </tr>`;
    }).join('');
}

async function loadScheduleOptions() {
    const [propertiesResponse, teamResponse] = await Promise.all([api('/properties'), api('/team')]);
    const properties = paginationData(propertiesResponse);
    document.getElementById('visit-property').innerHTML = '<option value="">Choose a property</option>' + properties
        .map((property) => `<option value="${property.id}">${escapeHtml(property.name || property.address_line_1)} · ${escapeHtml(property.customer?.name || '')}</option>`).join('');

    const technician = document.getElementById('visit-technician');
    teamRecords = paginationData(teamResponse);
    technician.innerHTML = '<option value="">Unassigned</option>' + teamRecords
        .filter((member) => member.role === 'technician')
        .map((member) => `<option value="${member.id}">${escapeHtml(member.name)}</option>`).join('');
}

async function initializeDashboard() {
    const [customers, properties, visits] = await Promise.all([api('/customers'), api('/properties'), api('/visits')]);
    const customerList = paginationData(customers);
    const propertyList = paginationData(properties);
    const visitList = paginationData(visits);
    const now = new Date();
    const currentMonth = now.getMonth();
    const currentYear = now.getFullYear();
    document.getElementById('stat-customers').textContent = customers.meta?.total ?? customerList.length;
    document.getElementById('stat-properties').textContent = properties.meta?.total ?? propertyList.length;
    document.getElementById('stat-upcoming').textContent = visitList.filter((visit) => visit.status === 'scheduled').length;
    document.getElementById('stat-completed').textContent = visitList.filter((visit) => {
        const completed = visit.completed_at ? new Date(visit.completed_at) : null;
        return visit.status === 'completed' && completed?.getMonth() === currentMonth && completed?.getFullYear() === currentYear;
    }).length;
    const upcoming = visitList.filter((visit) => visit.status === 'scheduled').slice(0, 6);
    const target = document.getElementById('dashboard-visits');
    if (!upcoming.length) {
        setTableMessage(target, 'No upcoming visits. Your schedule is clear.', 4);
        return;
    }
    target.innerHTML = upcoming.map((visit) => `
        <tr>
            <td><span class="table-primary-text">${escapeHtml(visit.property?.customer?.name || 'Customer')}</span><div class="table-secondary-text">${escapeHtml(visit.property?.name || visit.property?.address_line_1 || 'Property')}</div></td>
            <td>${escapeHtml(visit.technician?.name || 'Unassigned')}</td>
            <td>${escapeHtml(displayDate(visit.scheduled_at))}</td>
            <td>${statusBadge(visit.status)}</td>
        </tr>`).join('');
}

async function initializeBilling() {
    if (currentUser?.role !== 'admin') {
        window.location.assign('/visits');
        return;
    }

    const [plansResponse, subscriptionsResponse, customersResponse, invoicesResponse] = await Promise.all([
        api('/plans'),
        api('/subscriptions'),
        api('/customers'),
        api('/invoices'),
    ]);
    planRecords = paginationData(plansResponse);
    const plansTarget = document.getElementById('plans-table');
    plansTarget.innerHTML = planRecords.length
        ? planRecords.map((plan) => {
            const manageable = Number(plan.tenant_id) === Number(currentUser?.tenant_id);
            const actions = manageable
                ? `<button class="btn-table" data-action="edit-plan" data-id="${plan.id}">Edit</button><button class="btn-table danger" data-action="delete-plan" data-id="${plan.id}">Remove</button>`
                : '<span class="muted">Shared plan</span>';
            return `<tr>
                <td><span class="table-primary-text">${escapeHtml(plan.name)}</span><div class="table-secondary-text">${escapeHtml(plan.code)}</div></td>
                <td>${escapeHtml(displayMoney(plan.price, plan.currency))}</td>
                <td>${escapeHtml(intervalLabel(plan.billing_interval, plan.interval_count))}</td>
                <td>${plan.is_active ? '<span class="badge-status status-active">active</span>' : '<span class="badge-status status-inactive">inactive</span>'}</td>
                <td><div class="row-actions">${actions}</div></td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="5" class="empty-state">No service plans yet. Add a plan to offer subscriptions to customers.</td></tr>';

    const subscriptionRecords = paginationData(subscriptionsResponse);
    const customers = paginationData(customersResponse);
    const subscriptionByCustomer = new Map(subscriptionRecords
        .filter((subscription) => ['active', 'trialing'].includes(subscription.status))
        .map((subscription) => [Number(subscription.customer_id), subscription]));
    const subscriptionsTarget = document.getElementById('customer-subscriptions-table');
    subscriptionsTarget.innerHTML = customers.length
        ? customers.map((customer) => {
            const subscription = subscriptionByCustomer.get(Number(customer.id));
            const plan = subscription?.plan;
            const action = subscription
                ? `<button class="btn-table danger" data-action="cancel-customer-subscription" data-id="${customer.id}">Cancel</button>`
                : `<button class="btn-table" data-action="assign-customer-subscription" data-id="${customer.id}">Assign plan</button>`;
            return `<tr>
                <td><span class="table-primary-text">${escapeHtml(customer.name)}</span></td>
                <td>${escapeHtml(customer.email || '—')}</td>
                <td>${escapeHtml(plan?.name || '—')}</td>
                <td>${subscription ? statusBadge(subscription.status) : '<span class="muted">No active plan</span>'}</td>
                <td><div class="row-actions">${action}</div></td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="5" class="empty-state">Add customers before assigning service plans.</td></tr>';

    document.getElementById('subscription-customer').innerHTML = customers
        .map((customer) => `<option value="${customer.id}">${escapeHtml(customer.name)}</option>`).join('');
    const availablePlans = planRecords.filter((plan) => plan.is_active);
    document.getElementById('subscription-plan').innerHTML = availablePlans.length
        ? availablePlans.map((plan) => `<option value="${plan.id}">${escapeHtml(plan.name)} · ${escapeHtml(displayMoney(plan.price, plan.currency))} / ${escapeHtml(intervalLabel(plan.billing_interval, plan.interval_count))}</option>`).join('')
        : '<option value="">No active service plans</option>';

    const invoices = paginationData(invoicesResponse);
    const invoiceTarget = document.getElementById('invoices-table');
    if (!invoices.length) {
        setTableMessage(invoiceTarget, 'No customer invoices have been issued yet.', 8);
    } else {
        invoiceTarget.innerHTML = invoices.map((invoice) => `
            <tr>
                <td><span class="table-primary-text">${escapeHtml(invoice.invoice_number)}</span></td>
                <td>${escapeHtml(invoice.customer?.name || '—')}</td>
                <td>${escapeHtml(invoice.description)}</td>
                <td>${escapeHtml(invoice.issued_at ? displayDate(invoice.issued_at) : '—')}</td>
                <td>${escapeHtml(invoice.due_at ? displayDate(invoice.due_at) : '—')}</td>
                <td>${escapeHtml(displayMoney(invoice.total, invoice.currency))}</td>
                <td>${statusBadge(invoice.status)}</td>
                <td><button class="btn-table" data-action="download-invoice" data-id="${invoice.id}">PDF</button></td>
            </tr>`).join('');
    }

    subscriptionsTarget.onclick = async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const customerId = Number(button.dataset.id);
        if (button.dataset.action === 'assign-customer-subscription') {
            document.getElementById('subscription-customer').value = customerId;
            modal('customer-subscription-modal').show();
            return;
        }

        if (button.dataset.action !== 'cancel-customer-subscription'
            || !window.confirm('Cancel this customer service subscription?')) return;

        try {
            await api(`/customers/${customerId}/subscription`, { method: 'DELETE' });
            toast('Customer service subscription canceled.');
            await initializeBilling();
        } catch (error) {
            showError(error);
        }
    };

    plansTarget.onclick = async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const plan = planRecords.find((record) => Number(record.id) === Number(button.dataset.id));

        if (button.dataset.action === 'edit-plan' && plan && Number(plan.tenant_id) === Number(currentUser?.tenant_id)) {
            const form = document.getElementById('plan-form');
            form.reset();
            form.elements.id.value = plan.id;
            form.elements.name.value = plan.name;
            form.elements.code.value = plan.code;
            form.elements.price.value = plan.price;
            form.elements.currency.value = plan.currency;
            form.elements.billing_interval.value = plan.billing_interval;
            form.elements.interval_count.value = plan.interval_count;
            form.elements.description.value = plan.description || '';
            form.elements.features.value = (plan.features || []).join('\n');
            form.elements.is_active.checked = Boolean(plan.is_active);
            document.getElementById('plan-modal-title').textContent = 'Edit service plan';
            modal('plan-modal').show();
            return;
        }

        if (button.dataset.action === 'delete-plan' && plan && Number(plan.tenant_id) === Number(currentUser?.tenant_id)) {
            if (!window.confirm(`Remove ${plan.name}? Plans with customer subscriptions cannot be deleted. Deactivate those plans instead.`)) return;
            try {
                await api(`/plans/${plan.id}`, { method: 'DELETE' });
                toast('Service plan removed.');
                await initializeBilling();
            } catch (error) {
                showError(error);
            }
        }
    };

    document.querySelector('[data-action="new-plan"]').onclick = () => {
        const form = document.getElementById('plan-form');
        form.reset();
        form.elements.id.value = '';
        form.elements.currency.value = 'USD';
        form.elements.billing_interval.value = 'month';
        form.elements.interval_count.value = 1;
        form.elements.is_active.checked = true;
        document.getElementById('plan-modal-title').textContent = 'Add service plan';
        modal('plan-modal').show();
    };

    document.getElementById('plan-form').onsubmit = async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const planId = form.elements.id.value;
        const data = formObject(form);
        delete data.id;
        data.price = Number(data.price);
        data.interval_count = Number(data.interval_count);
        data.is_active = form.elements.is_active.checked;
        data.currency = data.currency.toUpperCase();
        data.features = data.features.split('\n').map((feature) => feature.trim()).filter(Boolean);
        if (!data.description) data.description = null;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            await api(planId ? `/plans/${planId}` : '/plans', {
                method: planId ? 'PATCH' : 'POST',
                body: data,
            });
            modal('plan-modal').hide();
            toast(planId ? 'Service plan updated.' : 'Service plan added.');
            await initializeBilling();
        } catch (error) {
            showError(error);
        } finally {
            submit.disabled = false;
        }
    };

    document.getElementById('customer-subscription-form').onsubmit = async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const customerId = form.elements.customer_id.value;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            await api(`/customers/${customerId}/subscription`, {
                method: 'POST',
                body: { plan_id: Number(form.elements.plan_id.value) },
            });
            modal('customer-subscription-modal').hide();
            toast('Service plan assigned to customer.');
            await initializeBilling();
        } catch (error) {
            showError(error);
        } finally {
            submit.disabled = false;
        }
    };

    invoiceTarget.onclick = async (event) => {
        const button = event.target.closest('[data-action="download-invoice"]');
        if (!button) return;

        try {
            const response = await fetch(`${apiBase}/invoices/${button.dataset.id}/download`, {
                headers: { Authorization: `Bearer ${readToken()}`, Accept: 'application/pdf, application/json' },
            });
            if (!response.ok) throw new Error('The invoice PDF could not be downloaded.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = `invoice-${button.dataset.id}.pdf`;
            link.click();
            URL.revokeObjectURL(url);
        } catch (error) {
            showError(error);
        }
    };
}

async function initializeTeam() {
    if (currentUser?.role !== 'admin') {
        window.location.assign('/visits');
        return;
    }

    await loadTeam();
    document.getElementById('team-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = event.currentTarget.querySelector('[type="submit"]');
        submit.disabled = true;
        try {
            await api('/team', { method: 'POST', body: formObject(event.currentTarget) });
            modal('team-modal').hide();
            event.currentTarget.reset();
            toast('Team member created.');
            await loadTeam();
        } catch (error) {
            showError(error);
        } finally {
            submit.disabled = false;
        }
    });
}

async function loadTeam() {
    const response = await api('/team');
    teamRecords = paginationData(response);
    const target = document.getElementById('team-table');
    if (!teamRecords.length) {
        setTableMessage(target, 'No team members found.', 4);
        return;
    }
    target.innerHTML = teamRecords.map((member) => `
        <tr>
            <td><span class="table-primary-text">${escapeHtml(member.name)}</span></td>
            <td>${escapeHtml(member.email)}</td>
            <td><span class="badge-status ${member.role === 'admin' ? 'status-in_progress' : 'status-active'}">${escapeHtml(member.role)}</span></td>
            <td>${escapeHtml(displayDate(member.created_at))}</td>
        </tr>`).join('');
}

function bindCustomerEvents() {
    document.querySelector('[data-action="new-customer"]')?.addEventListener('click', () => fillCustomerForm());
    document.getElementById('customers-table').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const customer = customerRecords.find((record) => Number(record.id) === Number(button.dataset.id));
        try {
            if (button.dataset.action === 'edit-customer' && customer) fillCustomerForm(customer);
            if (button.dataset.action === 'delete-customer' && customer && window.confirm(`Delete ${customer.name}? Its properties and visits will also be archived.`)) {
                await api(`/customers/${customer.id}`, { method: 'DELETE' });
                toast('Customer deleted.');
                await loadCustomers();
            }
        } catch (error) { showError(error); }
    });
    document.getElementById('customer-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = formObject(form);
        const id = data.id;
        delete data.id;
        try {
            await api(id ? `/customers/${id}` : '/customers', { method: id ? 'PATCH' : 'POST', body: data });
            modal('customer-modal').hide();
            toast(id ? 'Customer updated.' : 'Customer added.');
            await loadCustomers();
        } catch (error) { showError(error); }
    });
}

function bindPropertyEvents() {
    document.querySelector('[data-action="new-property"]')?.addEventListener('click', () => fillPropertyForm());
    document.getElementById('properties-table').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const property = propertyRecords.find((record) => Number(record.id) === Number(button.dataset.id));
        try {
            if (button.dataset.action === 'edit-property' && property) fillPropertyForm(property);
            if (button.dataset.action === 'delete-property' && property && window.confirm(`Delete property ${property.address_line_1}?`)) {
                await api(`/properties/${property.id}`, { method: 'DELETE' });
                toast('Property deleted.');
                await loadProperties();
            }
        } catch (error) { showError(error); }
    });
    document.getElementById('property-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const data = formObject(form);
        const id = data.id;
        delete data.id;
        Object.keys(data).forEach((key) => { if (data[key] === '') data[key] = null; });
        if (!data.access_code) delete data.access_code;
        try {
            await api(id ? `/properties/${id}` : '/properties', { method: id ? 'PATCH' : 'POST', body: data });
            modal('property-modal').hide();
            toast(id ? 'Property updated.' : 'Property added.');
            await loadProperties();
        } catch (error) { showError(error); }
    });
}

function bindVisitEvents() {
    document.querySelector('[data-action="new-visit"]')?.addEventListener('click', async () => {
        try {
            editingVisitId = null;
            await loadScheduleOptions();
            const form = document.getElementById('schedule-form');
            form.reset();
            const dateInput = document.getElementById('visit-scheduled');
            const tomorrow = new Date(Date.now() + 86400000);
            tomorrow.setMinutes(tomorrow.getMinutes() - tomorrow.getTimezoneOffset());
            dateInput.value = tomorrow.toISOString().slice(0, 16);
            document.getElementById('schedule-modal-title').textContent = 'Schedule a visit';
            form.querySelector('[type="submit"]').textContent = 'Schedule visit';
            modal('schedule-modal').show();
        } catch (error) { showError(error); }
    });

    document.getElementById('visit-status-filter').addEventListener('change', () => loadVisits().catch(showError));
    document.getElementById('visits-table').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const visitId = Number(button.dataset.id);
        try {
            if (button.dataset.action === 'visit-details') {
                await showVisitDetails(visitId);
                return;
            }
            if (button.dataset.action === 'create-visit-invoice') {
                const visit = visitRecords.find((record) => Number(record.id) === visitId);
                if (!visit || visit.status !== 'completed' || visit.invoice || currentUser?.role !== 'admin') return;

                const form = document.getElementById('visit-invoice-form');
                const customerSubscription = visit.property?.customer?.active_subscription;
                form.reset();
                form.elements.visit_id.value = visitId;
                form.elements.description.value = `Service visit #${visitId} - ${visit.property?.customer?.name || 'Customer'}`;
                form.elements.amount.value = customerSubscription ? '0.00' : '';
                form.elements.amount.min = customerSubscription ? '0' : '0.01';
                form.elements.amount.readOnly = false;
                form.elements.currency.value = customerSubscription?.plan?.currency || 'USD';
                form.elements.currency.readOnly = Boolean(customerSubscription);
                document.getElementById('visit-invoice-amount-label').textContent = customerSubscription ? 'Extra charges' : 'Service amount';
                document.getElementById('visit-invoice-subscription-note').textContent = customerSubscription
                    ? `The ${customerSubscription.plan?.name || 'active service'} subscription covers the visit. Enter any extra charges here; leave 0.00 if there are none.`
                    : 'Set the service charge for this visit. The invoice will be due in 14 days.';
                form.elements.currency.oninput = () => {
                    form.elements.currency.value = form.elements.currency.value.toUpperCase();
                };
                modal('visit-invoice-modal').show();
                return;
            }
            if (button.dataset.action === 'download-visit-invoice') {
                const invoiceId = visitId;
                const headers = new Headers({ Accept: 'application/pdf, application/json' });
                headers.set('Authorization', 'Bearer ' + readToken());
                const response = await fetch(`/api/invoices/${invoiceId}/download`, { headers });
                if (!response.ok) throw new Error('The visit invoice PDF could not be downloaded.');
                const url = URL.createObjectURL(await response.blob());
                const link = document.createElement('a');
                link.href = url;
                link.download = `invoice-${invoiceId}.pdf`;
                link.click();
                URL.revokeObjectURL(url);
                return;
            }
            if (button.dataset.action === 'edit-visit') {
                const visit = visitRecords.find((record) => Number(record.id) === visitId);
                if (!visit || visit.status !== 'scheduled') return;

                editingVisitId = visitId;
                await loadScheduleOptions();
                const form = document.getElementById('schedule-form');
                form.elements.property_id.value = visit.property_id;
                form.elements.assigned_to.value = visit.assigned_to || '';
                form.elements.scheduled_at.value = dateTimeLocalValue(visit.scheduled_at);
                form.elements.requested_services.value = (visit.requested_services || []).join('\n');
                document.getElementById('schedule-modal-title').textContent = 'Reschedule visit';
                form.querySelector('[type="submit"]').textContent = 'Save changes';
                modal('schedule-modal').show();
                return;
            }
            if (button.dataset.action === 'delete-visit') {
                const visit = visitRecords.find((record) => Number(record.id) === visitId);
                if (!visit || visit.status !== 'scheduled') return;
                if (!window.confirm('Delete this scheduled visit? This cannot be undone.')) return;

                await api(`/visits/${visitId}`, { method: 'DELETE' });
                toast('Scheduled visit deleted.');
                await loadVisits();
                return;
            }
            if (button.dataset.action === 'start-visit') {
                await api(`/visits/${visitId}/start`, { method: 'POST' });
                toast('Visit started.');
                await loadVisits();
            }
            if (button.dataset.action === 'complete-visit') {
                const form = document.getElementById('complete-form');
                form.reset();
                form.elements.visit_id.value = visitId;
                modal('complete-modal').show();
            }
        } catch (error) { showError(error); }
    });
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action="download-report"]');
        if (!button) return;
        event.preventDefault();

        try {
            const response = await fetch(button.href, {
                headers: { Authorization: `Bearer ${readToken()}`, Accept: 'application/pdf, application/json' },
            });
            if (!response.ok) throw new Error('The visit report could not be downloaded.');
            const url = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = url;
            link.download = `visit-${button.dataset.id}-report.pdf`;
            link.click();
            URL.revokeObjectURL(url);
        } catch (error) { showError(error); }
    });
    document.getElementById('schedule-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = formObject(event.currentTarget);
        data.assigned_to = data.assigned_to || null;
        data.scheduled_at = new Date(data.scheduled_at).toISOString();
        data.requested_services = data.requested_services
            .split('\n')
            .map((service) => service.trim())
            .filter(Boolean);
        try {
            await api(editingVisitId ? `/visits/${editingVisitId}` : '/visits', {
                method: editingVisitId ? 'PATCH' : 'POST',
                body: data,
            });
            modal('schedule-modal').hide();
            toast(editingVisitId ? 'Visit rescheduled.' : 'Visit scheduled.');
            editingVisitId = null;
            await loadVisits();
        } catch (error) { showError(error); }
    });
    document.getElementById('complete-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const visitId = form.elements.visit_id.value;
        const body = new FormData();
        for (const key of ['chlorine_reading', 'ph_reading', 'technician_notes']) {
            if (form.elements[key].value !== '') body.append(key, form.elements[key].value);
        }
        form.elements.services_performed.value.split('\n').map((line) => line.trim()).filter(Boolean)
            .forEach((service, index) => body.append(`services_performed[${index}]`, service));
        Array.from(form.elements.photos.files).forEach((photo, index) => body.append(`photos[${index}]`, photo));
        try {
            await api(`/visits/${visitId}/complete`, { method: 'POST', body });
            modal('complete-modal').hide();
            toast('Visit completed. Report generation is queued.');
            await loadVisits();
        } catch (error) { showError(error); }
    });
    document.getElementById('visit-invoice-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const visitId = form.elements.visit_id.value;
        const submit = form.querySelector('[type="submit"]');
        submit.disabled = true;

        try {
            const data = formObject(form);
            delete data.visit_id;
            data.currency = data.currency.toUpperCase();
            const invoice = await api(`/visits/${visitId}/invoice`, { method: 'POST', body: data });
            modal('visit-invoice-modal').hide();
            toast(`Invoice ${invoice.invoice_number} issued.`);
            await loadVisits();
        } catch (error) {
            showError(error);
        } finally {
            submit.disabled = false;
        }
    });
}

async function initializeAppPage() {
    if (!readToken()) {
        window.location.assign('/login');
        return;
    }

    try {
        await loadCurrentUser();
        bindLogout();
        const page = document.body.dataset.page;
        if (currentUser?.role !== 'admin' && ['dashboard', 'customers', 'properties', 'billing', 'team'].includes(page)) {
            window.location.assign('/visits');
            return;
        }
        if (page === 'dashboard') await initializeDashboard();
        if (page === 'customers') { await loadCustomers(); bindCustomerEvents(); }
        if (page === 'properties') { await loadProperties(); bindPropertyEvents(); }
        if (page === 'visits') { await loadVisits(); bindVisitEvents(); }
        if (page === 'billing') await initializeBilling();
        if (page === 'team') await initializeTeam();
    } catch (error) {
        if (readToken()) showError(error);
    }
}

async function showVisitDetails(visitId) {
    const target = document.getElementById('visit-details-content');
    target.innerHTML = '<div class="empty-state">Loading visit…</div>';
    modal('visit-details-modal').show();

    try {
        const visit = await api(`/visits/${visitId}`);
        const property = visit.property || {};
        const customer = property.customer || {};
        const services = Array.isArray(visit.services_performed) ? visit.services_performed : [];
        const photos = Array.isArray(visit.photos) ? visit.photos : [];
        const photoMarkup = photos.map((_, index) =>
            `<img class="visit-photo d-none" alt="Visit ${escapeHtml(visit.id)} photo ${index + 1}" data-photo-index="${index}">`,
        ).join('');

        document.getElementById('visit-details-title').textContent = customer.name || 'Visit details';
        document.getElementById('visit-details-subtitle').textContent = property.address_line_1 || 'Service visit';
        const requestedServices = Array.isArray(visit.requested_services) ? visit.requested_services : [];
        target.innerHTML = `
            <div class="visit-detail-grid">
                <div class="visit-detail-item"><span>Property</span><strong>${escapeHtml(property.name || property.address_line_1 || '—')}</strong></div>
                <div class="visit-detail-item"><span>Technician</span><strong>${escapeHtml(visit.technician?.name || 'Unassigned')}</strong></div>
                <div class="visit-detail-item"><span>Status</span><strong>${statusBadge(visit.status)}</strong></div>
                <div class="visit-detail-item"><span>Completed</span><strong>${escapeHtml(displayDate(visit.completed_at))}</strong></div>
                <div class="visit-detail-item"><span>Chlorine</span><strong>${escapeHtml(visit.chlorine_reading ?? 'Not recorded')}</strong></div>
                <div class="visit-detail-item"><span>pH</span><strong>${escapeHtml(visit.ph_reading ?? 'Not recorded')}</strong></div>
            </div>
            <div class="visit-detail-section"><h3>Services requested</h3>${requestedServices.length ? `<ul>${requestedServices.map((service) => `<li>${escapeHtml(service)}</li>`).join('')}</ul>` : '<p class="muted">No services were requested in advance.</p>'}</div>
            <div class="visit-detail-section"><h3>Services performed</h3>${services.length ? `<ul>${services.map((service) => `<li>${escapeHtml(service)}</li>`).join('')}</ul>` : '<p class="muted">No services recorded.</p>'}</div>
            <div class="visit-detail-section"><h3>Technician notes</h3><p>${escapeHtml(visit.technician_notes || 'No notes recorded.')}</p></div>
            <div class="visit-detail-section"><h3>Work photos</h3><div class="visit-photo-grid">${photoMarkup || '<p class="muted">No photos attached.</p>'}</div></div>
            ${visit.report_path ? `<a class="btn btn-primary" href="/api/visits/${visit.id}/report" data-action="download-report" data-id="${visit.id}">Download service report</a>` : ''}`;

        await Promise.all(photos.map(async (_, index) => {
            const response = await fetch(`${apiBase}/visits/${visitId}/photos/${index}`, {
                headers: { Authorization: `Bearer ${readToken()}`, Accept: 'image/*, application/json' },
            });
            if (!response.ok) throw new Error('A visit photo could not be loaded.');
            const image = target.querySelector(`[data-photo-index="${index}"]`);
            if (!image) return;
            image.src = URL.createObjectURL(await response.blob());
            image.classList.remove('d-none');
        }));
    } catch (error) {
        target.innerHTML = `<div class="form-error">${escapeHtml(error.message)}</div>`;
    }

    document.getElementById('visit-details-modal').addEventListener('hidden.bs.modal', () => {
        target.querySelectorAll('.visit-photo').forEach((image) => {
            if (image.src.startsWith('blob:')) URL.revokeObjectURL(image.src);
        });
    }, { once: true });
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.body.dataset.page === 'login') initializeLogin();
    else initializeAppPage();
});
