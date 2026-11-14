function ticketForm() {
    const form = document.getElementById('ticketDropDown');
    if (form) {
        form.classList.toggle('show');
    }
}

function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

function pretty(value) {
    return String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
}

function formatWhen(value) {
    if (!value) {
        return 'Not set';
    }
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString();
}

function updateDashboardCounters() {
    const ids = {
        new: 'newCount',
        in_progress: 'inProgressCount',
        resolved: 'resolvedCount',
        canceled: 'canceledCount'
    };

    fetch('php/get-ticket-counts.php')
        .then((response) => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then((data) => {
            if (data.error) {
                throw new Error(data.error);
            }
            Object.entries(ids).forEach(([key, id]) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = data[key];
                }
            });
        })
        .catch((error) => {
            console.error('Error getting ticket counts:', error);
            Object.values(ids).forEach((id) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = 'N/A';
                }
            });
        });
}

function appendComments(card, ticket, status) {
    const heading = document.createElement('h3');
    heading.className = 'section-label';
    heading.textContent = 'Comments';
    card.appendChild(heading);

    const list = document.createElement('div');
    list.className = 'comment-list';
    const comments = Array.isArray(ticket.comments) ? ticket.comments : [];

    if (comments.length === 0) {
        const empty = document.createElement('p');
        empty.className = 'form-note';
        empty.textContent = 'No comments yet.';
        list.appendChild(empty);
    }

    comments.forEach((comment) => {
        const item = document.createElement('article');
        item.className = 'comment';
        if (comment.author_role === 'system') {
            item.classList.add('system');
        }
        if (Number(comment.is_resolution) === 1) {
            item.classList.add('resolution');
        }

        const head = document.createElement('div');
        head.className = 'comment-head';
        const who = document.createElement('strong');
        const roleLabel = Number(comment.is_resolution) === 1
            ? 'Resolution note'
            : (comment.author_role === 'staff' ? 'IT Staff' : pretty(comment.author_role));
        who.textContent = comment.author_name + ' · ' + roleLabel;
        const time = document.createElement('time');
        time.textContent = formatWhen(comment.created_at);
        head.append(who, time);

        const body = document.createElement('p');
        body.textContent = comment.body;
        item.append(head, body);
        list.appendChild(item);
    });

    card.appendChild(list);

    if (status !== 'new' && status !== 'in_progress') {
        return;
    }

    const form = document.createElement('form');
    form.className = 'comment-form';

    const label = document.createElement('label');
    const fieldId = 'comment-' + ticket.id;
    label.htmlFor = fieldId;
    label.textContent = 'Add a comment';

    const input = document.createElement('textarea');
    input.id = fieldId;
    input.maxLength = 2000;
    input.rows = 3;
    input.placeholder = 'Ask a question or add more detail';

    const errorBox = document.createElement('p');
    errorBox.className = 'ticket-error';

    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'inline-button';
    submit.textContent = 'Submit comment';

    form.append(label, input, errorBox, submit);
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text) {
            errorBox.textContent = 'Enter a comment before submitting.';
            return;
        }

        submit.disabled = true;
        const body = new FormData();
        body.append('csrf_token', csrfToken());
        body.append('ticket_id', ticket.id);
        body.append('body', text);

        fetch('php/add-comment.php', { method: 'POST', body })
            .then((response) => response.json().then((data) => ({ response, data })))
            .then(({ response, data }) => {
                if (!response.ok) {
                    errorBox.textContent = data.error || 'Could not save the comment.';
                    submit.disabled = false;
                    return;
                }
                const active = document.querySelector('.summary-card.active');
                const labelText = active ? active.querySelector('.summary-label').textContent : 'New';
                loadTickets(status, labelText);
            })
            .catch(() => {
                errorBox.textContent = 'Could not save the comment.';
                submit.disabled = false;
            });
    });

    card.appendChild(form);
}

function loadTickets(status, label) {
    const list = document.getElementById('ticketList');
    document.getElementById('ticketListHeading').textContent = label + ' Tickets';
    document.querySelectorAll('.summary-card').forEach((card) => {
        card.classList.toggle('active', card.dataset.status === status);
    });
    list.textContent = 'Loading...';

    fetch('php/get-tickets.php?status=' + encodeURIComponent(status))
        .then((response) => {
            if (!response.ok) {
                throw new Error('HTTP error ' + response.status);
            }
            return response.json();
        })
        .then((tickets) => {
            list.textContent = '';
            if (!Array.isArray(tickets) || tickets.length === 0) {
                list.textContent = 'No tickets here.';
                return;
            }

            tickets.forEach((ticket) => {
                const card = document.createElement('article');
                card.className = 'ticket-item';

                const title = document.createElement('strong');
                title.textContent = '#' + ticket.id + ' ' + ticket.title;

                const meta = document.createElement('span');
                meta.className = 'ticket-meta';
                meta.textContent = 'Category: ' + pretty(ticket.category) +
                    ' · Priority: ' + pretty(ticket.priority) +
                    ' · Status: ' + pretty(ticket.status) +
                    (ticket.assigned_name ? ' · Assigned to: ' + ticket.assigned_name : '');

                const dates = document.createElement('span');
                dates.className = 'ticket-meta';
                dates.textContent = 'Submitted: ' + formatWhen(ticket.created_at) +
                    ' · Due: ' + formatWhen(ticket.dueDate);

                const description = document.createElement('p');
                description.className = 'ticket-description';
                description.textContent = ticket.description;

                card.append(title, meta, dates, description);
                if (status === 'canceled') {
                    const note = document.createElement('p');
                    note.className = 'form-note';
                    note.textContent = 'Canceled tickets stay canceled. Submit a new ticket if you still need help.';
                    card.appendChild(note);
                }
                appendComments(card, ticket, status);

                if (status === 'new' || status === 'in_progress') {
                    const actions = document.createElement('div');
                    actions.className = 'ticket-actions';
                    const errorBox = document.createElement('p');
                    errorBox.className = 'ticket-error';

                    const cancelButton = document.createElement('button');
                    cancelButton.type = 'button';
                    cancelButton.className = 'cancel-ticket-button';
                    cancelButton.textContent = 'Cancel Ticket';

                    const confirmRow = document.createElement('div');
                    confirmRow.className = 'confirm-row';
                    confirmRow.hidden = true;

                    const question = document.createElement('span');
                    question.textContent = 'Cancel this ticket? A canceled ticket cannot be reopened.';

                    const yesButton = document.createElement('button');
                    yesButton.type = 'button';
                    yesButton.className = 'cancel-ticket-button';
                    yesButton.textContent = 'Yes, cancel';

                    const noButton = document.createElement('button');
                    noButton.type = 'button';
                    noButton.className = 'keep-ticket-button';
                    noButton.textContent = 'Keep ticket';

                    confirmRow.append(question, yesButton, noButton);
                    cancelButton.addEventListener('click', () => {
                        cancelButton.hidden = true;
                        confirmRow.hidden = false;
                        errorBox.textContent = '';
                    });
                    noButton.addEventListener('click', () => {
                        confirmRow.hidden = true;
                        cancelButton.hidden = false;
                    });
                    yesButton.addEventListener('click', () => {
                        yesButton.disabled = true;
                        cancelTicket(ticket.id, status, label, errorBox, yesButton);
                    });

                    actions.append(cancelButton, confirmRow, errorBox);
                    card.appendChild(actions);
                }

                list.appendChild(card);
            });
        })
        .catch((error) => {
            console.error(error);
            list.textContent = 'Could not load tickets.';
        });
}

function cancelTicket(id, status, label, errorBox, yesButton) {
    const body = new FormData();
    body.append('id', id);
    body.append('csrf_token', csrfToken());

    fetch('php/cancel-ticket.php', { method: 'POST', body })
        .then((response) => response.json().then((data) => ({ response, data })))
        .then(({ response, data }) => {
            if (!response.ok) {
                errorBox.textContent = data.error || 'Could not cancel ticket.';
                yesButton.disabled = false;
                return;
            }
            loadTickets(status, label);
            updateDashboardCounters();
        })
        .catch(() => {
            errorBox.textContent = 'Could not cancel ticket.';
            yesButton.disabled = false;
        });
}

document.addEventListener('DOMContentLoaded', () => {
    updateDashboardCounters();

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('status') || urlParams.has('error')) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    document.querySelectorAll('.summary-card').forEach((card) => {
        const label = card.querySelector('.summary-label').textContent;
        const open = () => loadTickets(card.dataset.status, label);
        card.addEventListener('click', open);
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                open();
            }
        });
    });
});
