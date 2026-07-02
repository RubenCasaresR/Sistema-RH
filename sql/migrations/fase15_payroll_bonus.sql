CREATE TABLE IF NOT EXISTS payroll_bonus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    concepto VARCHAR(100) NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (period_id) REFERENCES payroll_periods(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    INDEX idx_period_employee (period_id, employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
