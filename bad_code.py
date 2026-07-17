import os
import sqlite3
import subprocess
import pickle
import hashlib

# Hardcoded credentials (CWE-798)
DB_PASSWORD = "supersecret123"
API_KEY = "sk-live-abc123def456ghi789"
SECRET_TOKEN = "ghp_realtoken1234567890abcdef"

# SQL Injection (CWE-89)
def get_user(username):
    conn = sqlite3.connect("app.db")
    cursor = conn.cursor()
    query = "SELECT * FROM users WHERE username = '" + username + "'"
    cursor.execute(query)
    return cursor.fetchall()

# Command Injection (CWE-78)
def run_report(filename):
    os.system("cat " + filename)

def ping_host(host):
    subprocess.call("ping -c 1 " + host, shell=True)

# Insecure deserialization (CWE-502)
def load_session(data):
    return pickle.loads(data)

# Weak hashing (CWE-327)
def hash_password(password):
    return hashlib.md5(password.encode()).hexdigest()

# Dead code / unused variables
def calculate_total(items):
    unused_var = "this does nothing"
    another_unused = 42
    total = 0
    for i in items:
        total = total + i
    result = total
    result = total * 1  # redundant
    return total

# Duplicate code block 1
def process_order(order_id):
    conn = sqlite3.connect("app.db")
    cursor = conn.cursor()
    query = "SELECT * FROM orders WHERE id = '" + str(order_id) + "'"
    cursor.execute(query)
    data = cursor.fetchall()
    conn.close()
    return data

# Duplicate code block 2 (near-identical to above)
def process_invoice(invoice_id):
    conn = sqlite3.connect("app.db")
    cursor = conn.cursor()
    query = "SELECT * FROM invoices WHERE id = '" + str(invoice_id) + "'"
    cursor.execute(query)
    data = cursor.fetchall()
    conn.close()
    return data

# Overly broad exception handling (swallows all errors)
def read_config(path):
    try:
        with open(path) as f:
            return f.read()
    except:
        pass

# Hardcoded IP address
def connect_to_service():
    host = "192.168.1.100"
    port = 8080
    return f"http://{host}:{port}"

# No input validation, path traversal risk (CWE-22)
def get_file(filename):
    base = "/var/www/uploads/"
    full_path = base + filename
    with open(full_path, "rb") as f:
        return f.read()

# Infinite recursion risk — no base case guard
def factorial(n):
    return n * factorial(n - 1)

# Global mutable state
app_state = {
    "logged_in_user": None,
    "admin_override": True,  # left on accidentally
}

# Empty function (code smell)
def validate_input(data):
    pass

# Magic numbers with no explanation
def calculate_price(quantity):
    return quantity * 9.99 * 1.175 * 0.92
