<?php
/**
 * ERP System - Customer Management
 * Manage restaurant customers and their information
 */

define('MINANG_SYSTEM', true);
require_once '../../config/config.php';
require_once '../includes/auth-check.php';

$customer = new Customer();
$action = $_GET['action'] ?? 'list';
$customerId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $customerData = [
            'customer_id' => sanitize($_POST['customer_id'] ?? ''),
            'name' => sanitize($_POST['name'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        $result = $customer->createCustomer($customerData);
        if ($result['success']) {
            $success = 'Customer created successfully';
            header('Location: customers.php?success=created');
            exit();
        } else {
            $error = $result['message'];
        }
    }
    
    if ($action === 'edit' && $customerId) {
        $customerData = [
            'name' => sanitize($_POST['name'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'address' => sanitize($_POST['address'] ?? ''),
            'notes' => sanitize($_POST['notes'] ?? '')
        ];
        
        $result = $customer->updateCustomer($customerId, $customerData);
        if ($result['success']) {
            $success = 'Customer updated successfully';
        } else {
            $error = $result['message'];
        }
    }
}

// Get data
$allCustomers = $customer->getAllCustomers();
$customerStats = $customer->getCustomerStats();
$topCustomers = $customer->getTopCustomers(5);
$currentCustomer = null;

if ($action === 'edit' && $customerId) {
    $currentCustomer = $customer->getCustomerById($customerId);
}

$pageTitle = 'Customer Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo BUSINESS_NAME; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?php echo PRIMARY_COLOR; ?>',
                        success: '<?php echo SUCCESS_COLOR; ?>',
                        warning: '<?php echo WARNING_COLOR; ?>',
                        danger: '<?php echo DANGER_COLOR; ?>'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include '../includes/sidebar.php'; ?>
    
    <div class="ml-64 min-h-screen">
        <?php include '../includes/header.php'; ?>
        
        <main class="p-8">
            <?php if ($action === 'list'): ?>
            <!-- Customer List -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Customer Management</h1>
                    <p class="text-gray-600">Manage restaurant customers and their information</p>
                </div>
                <a href="?action=add" class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-lg font-medium">
                    <i class="fas fa-plus mr-2"></i>Add New Customer
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Total Customers</p>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $customerStats['total_customers']; ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">Active Customers</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo $customerStats['active_customers']; ?></p>
                        </div>
                        <i class="fas fa-user-check text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">New Today</p>
                            <p class="text-2xl font-bold text-purple-600"><?php echo $customerStats['new_today']; ?></p>
                        </div>
                        <i class="fas fa-user-plus text-3xl text-purple-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-500 text-sm">New This Week</p>
                            <p class="text-2xl font-bold text-orange-600"><?php echo $customerStats['new_this_week']; ?></p>
                        </div>
                        <i class="fas fa-calendar-plus text-3xl text-orange-500"></i>
                    </div>
                </div>
            </div>

            <!-- Top Customers -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Top Customers</h2>
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <?php foreach ($topCustomers as $index => $topCustomer): ?>
                    <div class="text-center p-4 bg-gradient-to-b from-primary to-blue-600 text-white rounded-lg">
                        <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-2">
                            <span class="text-lg font-bold"><?php echo $index + 1; ?></span>
                        </div>
                        <h4 class="font-semibold text-sm mb-1"><?php echo $topCustomer['name']; ?></h4>
                        <p class="text-xs opacity-90"><?php echo $topCustomer['total_orders']; ?> orders</p>
                        <p class="text-xs font-bold"><?php echo formatCurrency($topCustomer['total_spent']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Customers Table -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-800">All Customers</h2>
                        <input type="text" id="customer-search" placeholder="Search customers..."
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full" id="customers-table">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <th class="px-6 py-4">Customer ID</th>
                                <th class="px-6 py-4">Name</th>
                                <th class="px-6 py-4">Phone</th>
                                <th class="px-6 py-4">Email</th>
                                <th class="px-6 py-4">Address</th>
                                <th class="px-6 py-4">Created Date</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($allCustomers as $customerItem): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-medium text-primary"><?php echo $customerItem['customer_id']; ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-gray-900"><?php echo $customerItem['name']; ?></div>
                                    <?php if ($customerItem['notes']): ?>
                                    <div class="text-sm text-gray-500 mt-1"><?php echo substr($customerItem['notes'], 0, 50) . (strlen($customerItem['notes']) > 50 ? '...' : ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <a href="tel:<?php echo $customerItem['phone']; ?>" class="text-primary hover:underline">
                                        <?php echo $customerItem['phone']; ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php if ($customerItem['email']): ?>
                                    <a href="mailto:<?php echo $customerItem['email']; ?>" class="text-primary hover:underline">
                                        <?php echo $customerItem['email']; ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600">
                                    <?php if ($customerItem['address']): ?>
                                    <div class="max-w-xs truncate"><?php echo $customerItem['address']; ?></div>
                                    <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-gray-600"><?php echo formatDate($customerItem['created_at']); ?></td>
                                <td class="px-6 py-4">
                                    <div class="flex space-x-2">
                                        <a href="?action=edit&id=<?php echo $customerItem['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button onclick="viewCustomerDetails(<?php echo $customerItem['id']; ?>)" 
                                                class="text-green-600 hover:text-green-800 text-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="deleteCustomer(<?php echo $customerItem['id']; ?>)" 
                                                class="text-red-600 hover:text-red-800 text-sm" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php elseif ($action === 'add' || $action === 'edit'): ?>
            <!-- Add/Edit Customer Form -->
            <div class="max-w-3xl">
                <div class="flex items-center justify-between mb-8">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">
                            <?php echo $action === 'add' ? 'Add New Customer' : 'Edit Customer'; ?>
                        </h1>
                        <p class="text-gray-600">
                            <?php echo $action === 'add' ? 'Register a new customer' : 'Update customer information'; ?>
                        </p>
                    </div>
                    <a href="customers.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>

                <div class="bg-white rounded-xl shadow-lg p-8">
                    <form method="POST" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php if ($action === 'add'): ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Customer ID</label>
                                <input type="text" name="customer_id" 
                                       value="<?php echo $currentCustomer['customer_id'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                       placeholder="Auto-generated if empty">
                                <p class="text-xs text-gray-500 mt-1">Format: CUS20240101001 (auto-generated)</p>
                            </div>
                            <?php else: ?>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Customer ID</label>
                                <input type="text" value="<?php echo $currentCustomer['customer_id'] ?? ''; ?>" readonly
                                       class="w-full p-3 border border-gray-300 bg-gray-100 rounded-lg">
                                <p class="text-xs text-gray-500 mt-1">Customer ID cannot be changed</p>
                            </div>
                            <?php endif; ?>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                                <input type="text" name="name" required
                                       value="<?php echo $currentCustomer['name'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                       placeholder="Enter customer's full name">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" name="phone" required
                                       value="<?php echo $currentCustomer['phone'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                       placeholder="+974-XXXX-XXXX">
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="email" 
                                       value="<?php echo $currentCustomer['email'] ?? ''; ?>"
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary"
                                       placeholder="customer@email.com">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                            <textarea name="address" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary resize-none"
                                      placeholder="Complete address for delivery orders"><?php echo $currentCustomer['address'] ?? ''; ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary resize-none"
                                      placeholder="Special notes about customer preferences or requirements"><?php echo $currentCustomer['notes'] ?? ''; ?></textarea>
                        </div>

                        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
                            <a href="customers.php" 
                               class="px-6 py-3 bg-gray-300 hover:bg-gray-400 text-gray-700 font-semibold rounded-lg transition-colors">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-6 py-3 bg-primary hover:bg-blue-600 text-white font-semibold rounded-lg transition-colors">
                                <i class="fas fa-save mr-2"></i>
                                <?php echo $action === 'add' ? 'Add Customer' : 'Update Customer'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Customer Details Modal -->
    <div id="customer-details-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Customer Details</h3>
                    <button onclick="hideCustomerDetailsModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="customer-details-content">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('customer-search').addEventListener('input', function() {
            const search = this.value.toLowerCase();
            const rows = document.querySelectorAll('#customers-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(search) ? '' : 'none';
            });
        });

        // View customer details
        async function viewCustomerDetails(customerId) {
            try {
                const response = await fetch(`../../api/customers.php?action=get_customer_details&customer_id=${customerId}`);
                const result = await response.json();
                
                if (result.success) {
                    const customer = result.customer;
                    const salesHistory = result.sales_history;
                    
                    let content = `
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Customer ID</label>
                                <p class="text-lg font-bold text-primary">${customer.customer_id}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Name</label>
                                <p class="text-lg font-semibold text-gray-900">${customer.name}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Phone</label>
                                <p class="text-gray-800">${customer.phone}</p>
                            </div>
                            <div>
                                <label class="text-sm font-semibold text-gray-600">Email</label>
                                <p class="text-gray-800">${customer.email || 'Not provided'}</p>
                            </div>
                        </div>
                        
                        ${customer.address ? `
                            <div class="mb-4">
                                <label class="text-sm font-semibold text-gray-600">Address</label>
                                <p class="text-gray-800">${customer.address}</p>
                            </div>
                        ` : ''}
                        
                        ${customer.notes ? `
                            <div class="mb-6">
                                <label class="text-sm font-semibold text-gray-600">Notes</label>
                                <p class="text-gray-800 bg-gray-50 p-3 rounded">${customer.notes}</p>
                            </div>
                        ` : ''}
                        
                        <div class="border-t pt-4">
                            <h4 class="font-semibold text-gray-900 mb-3">Recent Orders</h4>
                    `;
                    
                    if (salesHistory.length > 0) {
                        content += '<div class="space-y-2">';
                        salesHistory.forEach(sale => {
                            content += `
                                <div class="flex justify-between items-center p-3 bg-gray-50 rounded">
                                    <div>
                                        <div class="font-medium">${sale.receipt_number}</div>
                                        <div class="text-xs text-gray-500">${new Date(sale.created_at).toLocaleDateString()}</div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-semibold text-primary">QAR ${parseFloat(sale.total).toFixed(2)}</div>
                                        <div class="text-xs text-gray-500">${sale.cashier_name}</div>
                                    </div>
                                </div>
                            `;
                        });
                        content += '</div>';
                    } else {
                        content += '<p class="text-gray-500 text-center py-4">No order history</p>';
                    }
                    
                    content += '</div>';
                    
                    document.getElementById('customer-details-content').innerHTML = content;
                    document.getElementById('customer-details-modal').classList.remove('hidden');
                } else {
                    alert('Failed to load customer details');
                }
            } catch (error) {
                alert('Error loading customer details');
            }
        }

        function hideCustomerDetailsModal() {
            document.getElementById('customer-details-modal').classList.add('hidden');
        }

        // Delete customer
        async function deleteCustomer(customerId) {
            if (confirm('Are you sure you want to delete this customer?')) {
                try {
                    const response = await fetch('../../api/customers.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'delete_customer',
                            customer_id: customerId
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                    } else {
                        alert(result.message || 'Failed to delete customer');
                    }
                } catch (error) {
                    alert('Error deleting customer');
                }
            }
        }
    </script>
</body>
</html>