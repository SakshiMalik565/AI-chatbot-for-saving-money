<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$current_month = date('m');
$current_year = date('Y');

// Get monthly income and expenses for the last 6 months
$monthly_data_query = "SELECT 
    DATE_FORMAT(transaction_date, '%Y-%m') as month,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
FROM transactions
WHERE user_id = ? AND transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
ORDER BY month DESC";
$stmt = $conn->prepare($monthly_data_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$monthly_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get category-wise expenses for current month
$category_expenses_query = "SELECT 
    c.name as category_name,
    SUM(t.amount) as total_amount
FROM transactions t
JOIN categories c ON t.category_id = c.id
WHERE t.user_id = ? 
    AND t.type = 'expense'
    AND MONTH(t.transaction_date) = ?
    AND YEAR(t.transaction_date) = ?
GROUP BY c.name
ORDER BY total_amount DESC";
$stmt = $conn->prepare($category_expenses_query);
$stmt->bind_param("iii", $user_id, $current_month, $current_year);
$stmt->execute();
$category_expenses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get savings rate trend
$savings_rate_query = "SELECT 
    DATE_FORMAT(transaction_date, '%Y-%m') as month,
    ROUND(
        CASE 
            WHEN SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) > 0 
            THEN ((SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) - 
                  SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END)) / 
                  SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) * 100)
            ELSE 0 
        END, 2
    ) as savings_rate,
    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense
FROM (
    SELECT DATE_FORMAT(transaction_date, '%Y-%m-01') as transaction_date,
           type,
           amount
    FROM transactions 
    WHERE user_id = ? 
    AND transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
) as t
GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
ORDER BY month ASC";

$stmt = $conn->prepare($savings_rate_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$savings_rate_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get budget insights
$budget_insights_query = "SELECT 
    c.name as category_name,
    b.amount as budget_amount,
    COALESCE(SUM(t.amount), 0) as spent_amount,
    b.amount - COALESCE(SUM(t.amount), 0) as remaining_amount,
    (COALESCE(SUM(t.amount), 0) / b.amount * 100) as usage_percentage
FROM budgets b
LEFT JOIN categories c ON b.category_id = c.id
LEFT JOIN transactions t ON t.category_id = b.category_id 
    AND t.type = 'expense'
    AND MONTH(t.transaction_date) = ?
    AND YEAR(t.transaction_date) = ?
WHERE b.user_id = ?
GROUP BY b.id, c.name, b.amount
HAVING usage_percentage > 80 OR remaining_amount < 1000
ORDER BY usage_percentage DESC";

$stmt = $conn->prepare($budget_insights_query);
$stmt->bind_param("iii", $current_month, $current_year, $user_id);
$stmt->execute();
$budget_insights = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Generate AI insights based on budget data
$ai_insights = [];
foreach ($budget_insights as $insight) {
    if ($insight['usage_percentage'] > 100) {
        $ai_insights[] = "⚠️ {$insight['category_name']} budget exceeded by " . 
            number_format($insight['usage_percentage'] - 100, 1) . "%";
    } elseif ($insight['usage_percentage'] > 80) {
        $ai_insights[] = "⚠️ {$insight['category_name']} budget at " . 
            number_format($insight['usage_percentage'], 1) . "% usage";
    } elseif ($insight['remaining_amount'] < 1000) {
        $ai_insights[] = "📊 Only ₹" . number_format($insight['remaining_amount'], 2) . 
            " remaining in {$insight['category_name']} budget";
    }
}

// Calculate overall budget health
$overall_budget_query = "SELECT 
    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expenses,
    (SELECT SUM(amount) FROM budgets WHERE user_id = ?) as total_budget
FROM transactions 
WHERE user_id = ? 
    AND type = 'expense'
    AND MONTH(transaction_date) = ?
    AND YEAR(transaction_date) = ?";

$stmt = $conn->prepare($overall_budget_query);
$stmt->bind_param("iiii", $user_id, $user_id, $current_month, $current_year);
$stmt->execute();
$overall_budget = $stmt->get_result()->fetch_assoc();

$budget_health = "good";
if ($overall_budget['total_expenses'] > $overall_budget['total_budget']) {
    $budget_health = "critical";
} elseif ($overall_budget['total_expenses'] > ($overall_budget['total_budget'] * 0.8)) {
    $budget_health = "warning";
}

// Get AI recommendations
$recommendations_query = "SELECT * FROM ai_recommendations 
                         WHERE user_id = ? 
                         ORDER BY priority DESC, created_at DESC";
$stmt = $conn->prepare($recommendations_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recommendations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - AI Savings Assistant</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 600;
            font-size: 1.4rem;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }
        .insight-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .recommendation-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .priority-high {
            border-left: 4px solid #e74c3c;
        }
        .priority-medium {
            border-left: 4px solid #f39c12;
        }
        .priority-low {
            border-left: 4px solid #00b894;
        }
        .trend-up {
            color: #00b894;
        }
        .trend-down {
            color: #e74c3c;
        }
        
        /* Chatbot Styles */
        #chat-button {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00b894 0%, #00cec9 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        #chat-button i {
            font-size: 24px;
        }

        #chat-container {
            position: fixed;
            bottom: 90px;
            right: 20px;
            width: 350px;
            height: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            z-index: 999;
        }

        #chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
        }

        .chat-message {
            margin-bottom: 10px;
            max-width: 80%;
        }

        .user-message {
            margin-left: auto;
            background: #00b894;
            color: white;
            border-radius: 15px 15px 0 15px;
            padding: 10px 15px;
        }

        .bot-message {
            margin-right: auto;
            background: #f1f1f1;
            color: #2d3436;
            border-radius: 15px 15px 15px 0;
            padding: 10px 15px;
        }

        .chat-input-container {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        #chat-input {
            flex: 1;
            border: 1px solid #ddd;
            border-radius: 20px;
            padding: 8px 15px;
            outline: none;
        }

        #send-button {
            background: #00b894;
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-robot"></i> AI Savings Assistant
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="transactions.php">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="budgets.php">
                            <i class="fas fa-wallet"></i> Budgets
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="reports.php">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Monthly Income vs Expenses</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Category-wise Expenses</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Savings Rate Trend</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="savingsRateChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Financial Insights</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        if (!empty($monthly_data)) {
                            $current_month_data = $monthly_data[0];
                            $previous_month_data = isset($monthly_data[1]) ? $monthly_data[1] : null;
                            
                            // Calculate current month's savings rate
                            $current_savings_rate = 0;
                            if ($current_month_data['total_income'] > 0) {
                                $current_savings_rate = (($current_month_data['total_income'] - $current_month_data['total_expense']) / $current_month_data['total_income']) * 100;
                            }

                            // Current Month Summary
                            echo '<div class="insight-card">';
                            echo '<h6 class="mb-3">Current Month Overview</h6>';
                            echo '<p class="mb-2">';
                            echo '<i class="fas fa-money-bill-wave text-success"></i> Income: ₹' . number_format($current_month_data['total_income'], 2) . '<br>';
                            echo '<i class="fas fa-shopping-cart text-danger"></i> Expenses: ₹' . number_format($current_month_data['total_expense'], 2) . '<br>';
                            echo '<i class="fas fa-piggy-bank ' . ($current_savings_rate >= 20 ? 'text-success' : 'text-warning') . '"></i> ';
                            echo 'Savings Rate: ' . number_format($current_savings_rate, 1) . '%';
                            echo '</p>';
                            echo '</div>';
                            
                            if ($previous_month_data) {
                                $income_change = $current_month_data['total_income'] - $previous_month_data['total_income'];
                                $expense_change = $current_month_data['total_expense'] - $previous_month_data['total_expense'];
                                $savings_change = ($current_month_data['total_income'] - $current_month_data['total_expense']) - 
                                                ($previous_month_data['total_income'] - $previous_month_data['total_expense']);
                                
                                // Month-over-Month Changes
                                echo '<div class="insight-card">';
                                echo '<h6 class="mb-3">Month-over-Month Changes</h6>';
                                echo '<p class="mb-0">';
                                
                                // Income Change
                                echo '<div class="d-flex justify-content-between align-items-center mb-2">';
                                echo '<span>Income:</span>';
                                echo '<span class="' . ($income_change >= 0 ? 'text-success' : 'text-danger') . '">';
                                echo ($income_change >= 0 ? '↑' : '↓') . ' ₹' . number_format(abs($income_change), 2);
                                echo '</span></div>';
                                
                                // Expense Change
                                echo '<div class="d-flex justify-content-between align-items-center mb-2">';
                                echo '<span>Expenses:</span>';
                                echo '<span class="' . ($expense_change <= 0 ? 'text-success' : 'text-danger') . '">';
                                echo ($expense_change <= 0 ? '↓' : '↑') . ' ₹' . number_format(abs($expense_change), 2);
                                echo '</span></div>';
                                
                                // Savings Change
                                echo '<div class="d-flex justify-content-between align-items-center">';
                                echo '<span>Savings:</span>';
                                echo '<span class="' . ($savings_change >= 0 ? 'text-success' : 'text-danger') . '">';
                                echo ($savings_change >= 0 ? '↑' : '↓') . ' ₹' . number_format(abs($savings_change), 2);
                                echo '</span></div>';
                                echo '</p>';
                                echo '</div>';

                                // Key Observations
                                echo '<div class="insight-card">';
                                echo '<h6 class="mb-3">Key Observations</h6>';
                                echo '<ul class="list-unstyled mb-0">';
                                
                                // Income Observation
                                if (abs($income_change) > 0) {
                                    echo '<li class="mb-2"><i class="fas fa-chart-line ' . 
                                         ($income_change > 0 ? 'text-success' : 'text-danger') . '"></i> ' .
                                         'Income has ' . ($income_change > 0 ? 'increased' : 'decreased') . 
                                         ' by ' . number_format(abs($income_change / $previous_month_data['total_income'] * 100), 1) . '%</li>';
                                }
                                
                                // Expense Observation
                                if (abs($expense_change) > 0) {
                                    echo '<li class="mb-2"><i class="fas fa-chart-pie ' . 
                                         ($expense_change < 0 ? 'text-success' : 'text-danger') . '"></i> ' .
                                         'Expenses have ' . ($expense_change > 0 ? 'increased' : 'decreased') . 
                                         ' by ' . number_format(abs($expense_change / $previous_month_data['total_expense'] * 100), 1) . '%</li>';
                                }
                                
                                // Savings Rate Observation
                                $prev_savings_rate = $previous_month_data['total_income'] > 0 ? 
                                    (($previous_month_data['total_income'] - $previous_month_data['total_expense']) / $previous_month_data['total_income'] * 100) : 0;
                                $savings_rate_change = $current_savings_rate - $prev_savings_rate;
                                
                                if (abs($savings_rate_change) > 1) {
                                    echo '<li><i class="fas fa-piggy-bank ' . 
                                         ($savings_rate_change > 0 ? 'text-success' : 'text-danger') . '"></i> ' .
                                         'Savings rate has ' . ($savings_rate_change > 0 ? 'improved' : 'declined') . 
                                         ' by ' . number_format(abs($savings_rate_change), 1) . ' percentage points</li>';
                                }
                                
                                echo '</ul>';
                                echo '</div>';
                            }
                        } else {
                            echo '<div class="alert alert-info">No financial data available for analysis.</div>';
                        }
                        ?>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">AI Budget Insights</h5>
                    </div>
                    <div class="card-body">
                        <div class="budget-status mb-3">
                            <?php
                            $status_icon = $budget_health === 'good' ? '✅' : ($budget_health === 'warning' ? '⚠️' : '🚨');
                            $status_color = $budget_health === 'good' ? 'text-success' : ($budget_health === 'warning' ? 'text-warning' : 'text-danger');
                            ?>
                            <h6 class="<?php echo $status_color; ?>">
                                <?php echo $status_icon; ?> Overall Budget Status: 
                                <?php echo ucfirst($budget_health); ?>
                            </h6>
                            <p class="text-muted small mb-3">
                                Monthly Budget: ₹<?php echo number_format($overall_budget['total_budget'], 2); ?><br>
                                Total Spent: ₹<?php echo number_format($overall_budget['total_expenses'], 2); ?>
                            </p>
                        </div>

                        <?php if (!empty($ai_insights)): ?>
                            <div class="insights-list mb-3">
                                <?php foreach ($ai_insights as $insight): ?>
                                    <div class="alert alert-info py-2 mb-2">
                                        <?php echo $insight; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="chat-suggestion text-center">
                            <p class="mb-2">Need more detailed insights?</p>
                            <button class="btn btn-primary btn-sm" onclick="askBudgetInsights()">
                                <i class="fas fa-robot me-2"></i>Ask AI Assistant
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot Button and Container -->
    <button id="chat-button">
        <i class="fas fa-robot"></i>
    </button>

    <div id="chat-container" class="d-none">
        <div id="chat-messages"></div>
        <div class="chat-input-container">
            <input type="text" id="chat-input" placeholder="Type your message...">
            <button id="send-button">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/chat.js"></script>
    <script>
        // Monthly Income vs Expenses Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($monthly_data, 'month')); ?>,
                datasets: [{
                    label: 'Income',
                    data: <?php echo json_encode(array_column($monthly_data, 'total_income')); ?>,
                    backgroundColor: '#00b894',
                    borderColor: '#00b894',
                    borderWidth: 1
                }, {
                    label: 'Expenses',
                    data: <?php echo json_encode(array_column($monthly_data, 'total_expense')); ?>,
                    backgroundColor: '#e74c3c',
                    borderColor: '#e74c3c',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Category-wise Expenses Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($category_expenses, 'category_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($category_expenses, 'total_amount')); ?>,
                    backgroundColor: [
                        '#00b894', '#00cec9', '#0984e3', '#6c5ce7', '#e84393',
                        '#fd79a8', '#fdcb6e', '#e17055', '#d63031', '#636e72'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ₹' + context.raw.toLocaleString();
                            }
                        }
                    }
                }
            }
        });

        // Savings Rate Trend Chart
        const savingsRateCtx = document.getElementById('savingsRateChart').getContext('2d');
        new Chart(savingsRateCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($item) {
                    return date('M Y', strtotime($item['month']));
                }, $savings_rate_data)); ?>,
                datasets: [{
                    label: 'Savings Rate (%)',
                    data: <?php echo json_encode(array_map(function($item) {
                        return floatval($item['savings_rate']);
                    }, $savings_rate_data)); ?>,
                    borderColor: '#00b894',
                    backgroundColor: 'rgba(0, 184, 148, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Savings Rate: ' + context.raw.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        },
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        function askBudgetInsights() {
            // Show the chat container if hidden
            document.getElementById('chat-container').classList.remove('d-none');
            
            // Get the chat input and send button
            const chatInput = document.getElementById('chat-input');
            const sendButton = document.getElementById('send-button');
            
            // Get current financial data
            const currentData = {
                income: <?php echo !empty($monthly_data) ? $monthly_data[0]['total_income'] : 0; ?>,
                expenses: <?php echo !empty($monthly_data) ? $monthly_data[0]['total_expense'] : 0; ?>,
                savings_rate: <?php echo $current_savings_rate ?? 0; ?>
            };

            // Format category expenses
            const categoryExpenses = <?php 
                $formatted_expenses = [];
                foreach ($category_expenses as $expense) {
                    $formatted_expenses[] = [
                        'category' => $expense['category_name'],
                        'amount' => $expense['total_amount']
                    ];
                }
                echo json_encode($formatted_expenses);
            ?>;

            // Create a detailed prompt with the financial data
            let prompt = "Based on my current financial data:\n";
            prompt += `Current Month Income: ₹${currentData.income.toLocaleString()}\n`;
            prompt += `Current Month Expenses: ₹${currentData.expenses.toLocaleString()}\n`;
            prompt += `Current Savings Rate: ${currentData.savings_rate.toFixed(1)}%\n\n`;
            
            if (categoryExpenses.length > 0) {
                prompt += "Category-wise Expenses:\n";
                categoryExpenses.forEach(exp => {
                    prompt += `- ${exp.category}: ₹${exp.amount.toLocaleString()}\n`;
                });
                prompt += "\n";
            }

            <?php if (!empty($monthly_data) && isset($monthly_data[1])) { ?>
                const previousData = {
                    income: <?php echo $monthly_data[1]['total_income']; ?>,
                    expenses: <?php echo $monthly_data[1]['total_expense']; ?>
                };

                const changes = {
                    income: currentData.income - previousData.income,
                    expenses: currentData.expenses - previousData.expenses
                };

                prompt += "Month-over-Month Changes:\n";
                prompt += `Income: ${changes.income >= 0 ? '↑' : '↓'} ₹${Math.abs(changes.income).toLocaleString()}\n`;
                prompt += `Expenses: ${changes.expenses >= 0 ? '↑' : '↓'} ₹${Math.abs(changes.expenses).toLocaleString()}\n\n`;
            <?php } ?>

            prompt += "Please analyze this data and provide:\n";
            prompt += "1. Key insights about my spending patterns\n";
            prompt += "2. Specific recommendations for improvement\n";
            prompt += "3. Suggestions for achieving better savings rate";
            
            // Set the prompt in the chat input
            chatInput.value = prompt;
            
            // Trigger the send button click
            sendButton.click();
        }
    </script>
</body>
</html> 