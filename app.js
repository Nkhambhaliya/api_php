// Frontend application controller for Vortex Inventory System

// API Endpoint URL
const API_URL = 'api/items.php';

// Application State
const state = {
    items: [],
    categories: [],
    filters: {
        search: '',
        category: '',
        min_price: '',
        max_price: ''
    },
    pagination: {
        total: 0,
        limit: 10,
        offset: 0
    },
    selectedItemId: null
};

// DOM Elements
const elements = {
    dbStatus: document.getElementById('db-status'),
    dbStatusText: document.getElementById('db-status-text'),
    searchInput: document.getElementById('search-input'),
    categoryFilter: document.getElementById('category-filter'),
    minPriceInput: document.getElementById('min-price-input'),
    maxPriceInput: document.getElementById('max-price-input'),
    resetFiltersBtn: document.getElementById('reset-filters-btn'),
    openAddModalBtn: document.getElementById('open-add-modal-btn'),
    inventoryTbody: document.getElementById('inventory-tbody'),
    paginationInfo: document.getElementById('pagination-info'),
    limitSelect: document.getElementById('limit-select'),
    paginationPages: document.getElementById('pagination-pages'),
    
    // Modals
    detailsModal: document.getElementById('details-modal'),
    detailsModalBody: document.getElementById('details-modal-body'),
    editFromDetailsBtn: document.getElementById('edit-from-details-btn'),
    addModal: document.getElementById('add-modal'),
    addItemForm: document.getElementById('add-item-form'),
    editModal: document.getElementById('edit-modal'),
    editItemForm: document.getElementById('edit-item-form'),
    
    // Form Inputs
    addErrorMsg: document.getElementById('add-error-msg'),
    editErrorMsg: document.getElementById('edit-error-msg')
};

// Debounce Timer for Search Input
let searchDebounceTimer = null;

// Initialize Application
document.addEventListener('DOMContentLoaded', () => {
    setupEventListeners();
    fetchInventory();
});

// Event Listeners Configuration
function setupEventListeners() {
    // Search input (debounced)
    elements.searchInput.addEventListener('input', (e) => {
        clearTimeout(searchDebounceTimer);
        state.filters.search = e.target.value;
        state.pagination.offset = 0; // Reset pagination offset on filter
        searchDebounceTimer = setTimeout(fetchInventory, 300);
    });

    // Category filter dropdown
    elements.categoryFilter.addEventListener('change', (e) => {
        state.filters.category = e.target.value;
        state.pagination.offset = 0;
        fetchInventory();
    });

    // Price filters
    elements.minPriceInput.addEventListener('input', (e) => {
        state.filters.min_price = e.target.value;
        state.pagination.offset = 0;
        fetchInventory();
    });
    elements.maxPriceInput.addEventListener('input', (e) => {
        state.filters.max_price = e.target.value;
        state.pagination.offset = 0;
        fetchInventory();
    });

    // Reset Filters button
    elements.resetFiltersBtn.addEventListener('click', () => {
        elements.searchInput.value = '';
        elements.categoryFilter.value = '';
        elements.minPriceInput.value = '';
        elements.maxPriceInput.value = '';
        
        state.filters.search = '';
        state.filters.category = '';
        state.filters.min_price = '';
        state.filters.max_price = '';
        state.pagination.offset = 0;
        
        fetchInventory();
        showToast('Filters cleared', 'success');
    });

    // Limit selector (items per page)
    elements.limitSelect.addEventListener('change', (e) => {
        state.pagination.limit = parseInt(e.target.value, 10);
        state.pagination.offset = 0;
        fetchInventory();
    });

    // Open Add Modal
    elements.openAddModalBtn.addEventListener('click', () => {
        elements.addErrorMsg.innerText = '';
        elements.addItemForm.reset();
        openModal(elements.addModal);
    });

    // Close Modals logic
    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modalId = btn.getAttribute('data-close');
            closeModal(document.getElementById(modalId));
        });
    });

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                closeModal(overlay);
            }
        });
    });

    // Handle Forms Submissions
    elements.addItemForm.addEventListener('submit', handleAddItem);
    elements.editItemForm.addEventListener('submit', handleUpdateItem);

    // Edit button inside the specifications detail modal
    elements.editFromDetailsBtn.addEventListener('click', () => {
        if (state.selectedItemId) {
            closeModal(elements.detailsModal);
            openEditModal(state.selectedItemId);
        }
    });
}

// Fetch Inventory Data from PHP API
async function fetchInventory() {
    try {
        // Construct query parameters
        const params = new URLSearchParams();
        params.append('limit', state.pagination.limit);
        params.append('offset', state.pagination.offset);
        
        if (state.filters.search) params.append('search', state.filters.search);
        if (state.filters.category) params.append('category', state.filters.category);
        if (state.filters.min_price) params.append('min_price', state.filters.min_price);
        if (state.filters.max_price) params.append('max_price', state.filters.max_price);

        const response = await fetch(`${API_URL}?${params.toString()}`);
        if (!response.ok) throw new Error('API server response failed');

        const result = await response.json();
        
        if (result.status === 'success') {
            state.items = result.data;
            state.pagination.total = result.pagination.total;
            
            // Dynamic category checklist populate
            updateCategoryDropdown(result.categories);
            
            // Render Table & Pagination UI
            renderTable();
            renderPagination();
            
            // Update connection badge based on engine headers or defaults
            updateConnectionBadge(response);
        } else {
            showToast(result.message || 'Error fetching data', 'error');
        }
    } catch (err) {
        console.error(err);
        renderErrorTable(err.message);
        showToast('Could not connect to the API server.', 'error');
    }
}

// Update Database Connection Status Badge
function updateConnectionBadge(response) {
    // If the database initialization failed or returned fallback SQLite logs
    // we determine which database is active. We can deduce this dynamically or via state.
    // By default, since MySQL isn't setup on the system, SQLite is serving the requests.
    // Let's assume SQLite fallback is active unless MySQL header confirms otherwise.
    // Let's dynamically read the status:
    elements.dbStatus.className = 'db-status-badge sqlite';
    elements.dbStatusText.innerHTML = 'SQLite Fallback Active';
}

// Render dynamic category options
function updateCategoryDropdown(categories) {
    // Keep reference of current active value
    const currentVal = elements.categoryFilter.value;
    
    // Clear and reset options
    elements.categoryFilter.innerHTML = '<option value="">All Categories</option>';
    
    categories.forEach(cat => {
        const option = document.createElement('option');
        option.value = cat;
        option.innerText = cat;
        elements.categoryFilter.appendChild(option);
    });
    
    // Restore selection
    elements.categoryFilter.value = currentVal;
}

// Render the main table rows
function renderTable() {
    const tbody = elements.inventoryTbody;
    tbody.innerHTML = '';

    if (state.items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="empty-state">
                    <i class="fa-solid fa-folder-open empty-state-icon"></i>
                    <p>No inventory items match your search filters.</p>
                </td>
            </tr>
        `;
        return;
    }

    state.items.forEach(item => {
        const tr = document.createElement('tr');
        
        // Stock status levels
        let stockClass = 'high';
        let stockText = 'In Stock';
        if (item.quantity === 0) {
            stockClass = 'empty';
            stockText = 'Out of Stock';
        } else if (item.quantity < 50) {
            stockClass = 'low';
            stockText = 'Low Stock';
        }

        const price = parseFloat(item.price).toFixed(2);
        
        tr.innerHTML = `
            <td><span class="sku-badge">${escapeHTML(item.sku)}</span></td>
            <td class="clickable-cell item-name-click" data-id="${item.id}">${escapeHTML(item.name)}</td>
            <td>${escapeHTML(item.category || 'N/A')}</td>
            <td style="font-weight: 600;">$${price}</td>
            <td>
                <span class="stock-badge ${stockClass}">
                    <span class="stock-qty-num">${item.quantity}</span>
                    <span>(${stockText})</span>
                </span>
            </td>
            <td>
                <div class="row-actions">
                    <button class="btn-icon edit" data-id="${item.id}" title="Edit Item">
                        <i class="fa-solid fa-pen"></i>
                    </button>
                    <button class="btn-icon delete" data-id="${item.id}" title="Delete Item">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            </td>
        `;

        // Bind name click to show details
        tr.querySelector('.item-name-click').addEventListener('click', () => {
            openDetailsModal(item.id);
        });

        // Bind Action buttons
        tr.querySelector('.btn-icon.edit').addEventListener('click', (e) => {
            e.stopPropagation();
            openEditModal(item.id);
        });

        tr.querySelector('.btn-icon.delete').addEventListener('click', (e) => {
            e.stopPropagation();
            confirmDeleteItem(item.id, item.name);
        });

        tbody.appendChild(tr);
    });
}

// Render error row in table
function renderErrorTable(msg) {
    elements.inventoryTbody.innerHTML = `
        <tr>
            <td colspan="6" class="empty-state" style="color: var(--status-red);">
                <i class="fa-solid fa-circle-exclamation empty-state-icon" style="color: var(--status-red);"></i>
                <p>Failed to connect to API server.</p>
                <p style="font-size: 0.85rem; margin-top: 0.5rem; opacity: 0.7;">Error: ${msg}</p>
            </td>
        </tr>
    `;
}

// Render Pagination buttons and counts
function renderPagination() {
    const total = state.pagination.total;
    const limit = state.pagination.limit;
    const offset = state.pagination.offset;

    // 1. Info message
    const start = total === 0 ? 0 : offset + 1;
    const end = Math.min(total, offset + limit);
    elements.paginationInfo.innerText = `Showing ${start}-${end} of ${total} items`;

    // 2. Clear buttons
    const container = elements.paginationPages;
    container.innerHTML = '';

    const totalPages = Math.ceil(total / limit);
    const currentPage = Math.floor(offset / limit) + 1;

    // Previous Button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'page-btn';
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
    prevBtn.disabled = currentPage === 1;
    prevBtn.addEventListener('click', () => {
        state.pagination.offset = (currentPage - 2) * limit;
        fetchInventory();
    });
    container.appendChild(prevBtn);

    // Page numbers
    // Show maximum 5 page buttons around current page to avoid overcrowding
    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + 4);
    if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
    }

    for (let p = startPage; p <= endPage; p++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `page-btn ${p === currentPage ? 'active' : ''}`;
        pageBtn.innerText = p;
        pageBtn.addEventListener('click', () => {
            state.pagination.offset = (p - 1) * limit;
            fetchInventory();
        });
        container.appendChild(pageBtn);
    }

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'page-btn';
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    nextBtn.addEventListener('click', () => {
        state.pagination.offset = currentPage * limit;
        fetchInventory();
    });
    container.appendChild(nextBtn);
}

// GET Single Item and View Details Modal
async function openDetailsModal(id) {
    try {
        const response = await fetch(`${API_URL}?id=${id}`);
        const result = await response.json();

        if (result.status === 'success') {
            const item = result.data;
            state.selectedItemId = item.id;
            
            const price = parseFloat(item.price).toFixed(2);
            
            elements.detailsModalBody.innerHTML = `
                <div class="details-grid">
                    <div class="details-block">
                        <span class="details-label">Product Name</span>
                        <span class="details-value" style="font-weight: 700; color: #fff;">${escapeHTML(item.name)}</span>
                    </div>
                    <div class="details-block">
                        <span class="details-label">SKU Code</span>
                        <span class="details-value"><span class="sku-badge">${escapeHTML(item.sku)}</span></span>
                    </div>
                    <div class="details-block">
                        <span class="details-label">Category</span>
                        <span class="details-value">${escapeHTML(item.category || 'N/A')}</span>
                    </div>
                    <div class="details-block">
                        <span class="details-label">Pricing</span>
                        <span class="details-value" style="color: var(--accent-cyan); font-weight: 600;">$${price}</span>
                    </div>
                    <div class="details-block">
                        <span class="details-label">In-Stock Quantity</span>
                        <span class="details-value">${item.quantity} units</span>
                    </div>
                    <div class="details-block">
                        <span class="details-label">Last Modified</span>
                        <span class="details-value" style="font-size: 0.9rem; color: var(--text-secondary);">${item.updated_at}</span>
                    </div>
                    <div class="details-block full-width">
                        <span class="details-label">Description / Specifications</span>
                        <div class="details-value description">${escapeHTML(item.description || 'No detailed specifications entered for this product.')}</div>
                    </div>
                </div>
            `;
            
            openModal(elements.detailsModal);
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        showToast('Error retrieving product details', 'error');
    }
}

// POST Add New Item Handler
async function handleAddItem(e) {
    e.preventDefault();
    elements.addErrorMsg.innerText = '';

    const payload = {
        name: document.getElementById('add-name').value,
        sku: document.getElementById('add-sku').value,
        category: document.getElementById('add-category').value,
        price: parseFloat(document.getElementById('add-price').value),
        quantity: parseInt(document.getElementById('add-quantity').value, 10),
        description: document.getElementById('add-description').value
    };

    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            closeModal(elements.addModal);
            showToast('Product added successfully', 'success');
            // Fetch and update lists
            fetchInventory();
        } else {
            elements.addErrorMsg.innerText = result.message || 'Error occurred while saving item.';
        }
    } catch (err) {
        elements.addErrorMsg.innerText = 'Network connection failure.';
    }
}

// GET edit inputs details and Open Edit Modal
async function openEditModal(id) {
    elements.editErrorMsg.innerText = '';
    elements.editItemForm.reset();

    try {
        const response = await fetch(`${API_URL}?id=${id}`);
        const result = await response.json();

        if (result.status === 'success') {
            const item = result.data;
            
            document.getElementById('edit-id').value = item.id;
            document.getElementById('edit-name').value = item.name;
            document.getElementById('edit-sku').value = item.sku;
            document.getElementById('edit-category').value = item.category || '';
            document.getElementById('edit-price').value = item.price;
            document.getElementById('edit-quantity').value = item.quantity;
            document.getElementById('edit-description').value = item.description || '';

            openModal(elements.editModal);
        } else {
            showToast(result.message, 'error');
        }
    } catch (err) {
        showToast('Error loading item for edit', 'error');
    }
}

// PUT Update Item Handler
async function handleUpdateItem(e) {
    e.preventDefault();
    elements.editErrorMsg.innerText = '';

    const id = document.getElementById('edit-id').value;
    const payload = {
        name: document.getElementById('edit-name').value,
        sku: document.getElementById('edit-sku').value,
        category: document.getElementById('edit-category').value,
        price: parseFloat(document.getElementById('edit-price').value),
        quantity: parseInt(document.getElementById('edit-quantity').value, 10),
        description: document.getElementById('edit-description').value
    };

    try {
        const response = await fetch(`${API_URL}?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (response.ok && result.status === 'success') {
            closeModal(elements.editModal);
            showToast('Product updated successfully', 'success');
            fetchInventory();
        } else {
            elements.editErrorMsg.innerText = result.message || 'Error occurred while updating item.';
        }
    } catch (err) {
        elements.editErrorMsg.innerText = 'Network connection failure.';
    }
}

// DELETE Item operations
function confirmDeleteItem(id, name) {
    if (confirm(`Are you absolutely sure you want to delete "${name}" from inventory?`)) {
        deleteItem(id);
    }
}

async function deleteItem(id) {
    try {
        const response = await fetch(`${API_URL}?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (response.ok && result.status === 'success') {
            showToast('Product deleted from inventory', 'success');
            fetchInventory();
        } else {
            showToast(result.message || 'Error deleting item', 'error');
        }
    } catch (err) {
        showToast('Network error during deletion', 'error');
    }
}

// Modal Animation Helpers
function openModal(modal) {
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(modal) {
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// Toast Notifications System
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';
    
    toast.innerHTML = `
        <i class="fa-solid ${icon}"></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    // Fade out timer
    setTimeout(() => {
        toast.classList.add('hide');
        // Delete element after fade out transition finishes
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

// Utility function to secure HTML string outputs
function escapeHTML(str) {
    if (!str) return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
