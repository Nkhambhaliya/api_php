# Sandbox server helper simulating PHP items.php REST API using python sqlite3
import http.server
import socketserver
import urllib.parse
import sqlite3
import json
import os

PORT = 8000
DB_FILE = os.path.join(os.path.dirname(__file__), 'inventory.db')

def init_db():
    if not os.path.exists(DB_FILE):
        print("[Database] Creating new sandbox SQLite database: inventory.db")
        conn = sqlite3.connect(DB_FILE)
        cursor = conn.cursor()
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                sku TEXT UNIQUE NOT NULL,
                description TEXT,
                price REAL NOT NULL DEFAULT 0.0,
                quantity INTEGER NOT NULL DEFAULT 0,
                category TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        """)
        
        dummy_items = [
            ('Wireless Ergonomic Mouse', 'MS-ERG-01', 'A comfortable vertical wireless mouse designed for long hours of office work.', 49.99, 120, 'Electronics'),
            ('Mechanical Keyboard', 'KB-MECH-02', 'RGB Backlit mechanical keyboard with brown tactile switches.', 89.95, 75, 'Electronics'),
            ('Office Ergonomic Chair', 'CH-ERG-03', 'High-back mesh office chair with lumbar support and adjustable armrests.', 189.00, 30, 'Furniture'),
            ('Standing Desk Converter', 'DK-STND-04', 'Dual monitor height adjustable sit-to-stand converter.', 149.50, 45, 'Furniture'),
            ('Stainless Steel Water Bottle', 'BT-SST-05', 'Double-wall vacuum insulated water bottle (32oz), keeps drinks cold for 24h.', 24.99, 250, 'Kitchenware'),
            ('USB-C Hub Multiport Adapter', 'HB-USBC-06', '7-in-1 USB-C hub with 4K HDMI, 3 USB 3.0 ports, SD/TF card reader, and 100W PD.', 34.99, 150, 'Electronics'),
            ('Noise Cancelling Headphones', 'HP-ANC-07', 'Over-ear active noise cancelling bluetooth headphones with 40h playtime.', 129.99, 60, 'Electronics'),
            ('Leather Portfolio Notebook', 'NB-LTH-08', 'Premium refillable A5 leather journal for writing and drawing.', 19.99, 300, 'Office Supplies'),
            ('Smart LED Desk Lamp', 'LP-LED-09', 'Dimmable eye-caring desk light with USB charging port and 5 color modes.', 39.99, 90, 'Furniture'),
            ('Bluetooth Trackball Mouse', 'MS-TRK-10', 'Ergonomic trackball mouse with adjustable DPI and rechargeable battery.', 59.99, 50, 'Electronics')
        ]
        
        cursor.executemany("""
            INSERT INTO items (name, sku, description, price, quantity, category)
            VALUES (?, ?, ?, ?, ?, ?)
        """, dummy_items)
        conn.commit()
        conn.close()
        print("[Database] Database initialized and seeded successfully.")

class InventoryHandler(http.server.SimpleHTTPRequestHandler):
    def end_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With')
        super().end_headers()

    def do_OPTIONS(self):
        self.send_response(200)
        self.end_headers()

    def do_GET(self):
        parsed_url = urllib.parse.urlparse(self.path)
        path = parsed_url.path
        
        # Static Router
        if path in ['', '/', '/index.html']:
            self.serve_static('index.html', 'text/html')
            return
        elif path == '/style.css':
            self.serve_static('style.css', 'text/css')
            return
        elif path == '/app.js':
            self.serve_static('app.js', 'application/javascript')
            return
        
        # API Router
        if path == '/api/items.php':
            self.handle_api_get(parsed_url.query)
            return
            
        super().do_GET()

    def do_POST(self):
        parsed_url = urllib.parse.urlparse(self.path)
        if parsed_url.path == '/api/items.php':
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            try:
                input_data = json.loads(post_data.decode('utf-8'))
                self.handle_api_post(input_data)
            except Exception as e:
                self.send_json({"status": "error", "message": f"Invalid JSON: {str(e)}"}, 400)
        else:
            self.send_json({"status": "error", "message": "Not Found"}, 404)

    def do_PUT(self):
        parsed_url = urllib.parse.urlparse(self.path)
        if parsed_url.path == '/api/items.php':
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            params = urllib.parse.parse_qs(parsed_url.query)
            
            if 'id' not in params:
                self.send_json({"status": "error", "message": "Invalid or missing Item ID."}, 400)
                return
                
            item_id = int(params['id'][0])
            try:
                input_data = json.loads(post_data.decode('utf-8'))
                self.handle_api_put(item_id, input_data)
            except Exception as e:
                self.send_json({"status": "error", "message": f"Invalid JSON: {str(e)}"}, 400)
        else:
            self.send_json({"status": "error", "message": "Not Found"}, 404)

    def do_DELETE(self):
        parsed_url = urllib.parse.urlparse(self.path)
        if parsed_url.path == '/api/items.php':
            params = urllib.parse.parse_qs(parsed_url.query)
            if 'id' not in params:
                self.send_json({"status": "error", "message": "Invalid or missing Item ID."}, 400)
                return
                
            item_id = int(params['id'][0])
            self.handle_api_delete(item_id)
        else:
            self.send_json({"status": "error", "message": "Not Found"}, 404)

    # --- API Controllers ---

    def handle_api_get(self, query_str):
        params = urllib.parse.parse_qs(query_str)
        conn = sqlite3.connect(DB_FILE)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        # 1. Single Item details
        if 'id' in params:
            try:
                item_id = int(params['id'][0])
                cursor.execute("SELECT * FROM items WHERE id = ?", (item_id,))
                row = cursor.fetchone()
                if row:
                    self.send_json({"status": "success", "data": dict(row)})
                else:
                    self.send_json({"status": "error", "message": "Item not found"}, 404)
            except Exception as e:
                self.send_json({"status": "error", "message": str(e)}, 500)
            finally:
                conn.close()
            return

        # 2. Paginated and filtered lists
        try:
            limit = int(params.get('limit', [10])[0])
            offset = int(params.get('offset', [0])[0])
            
            conditions = []
            sql_params = []
            
            if 'search' in params and params['search'][0]:
                search_val = f"%{params['search'][0]}%"
                conditions.append("(name LIKE ? OR sku LIKE ? OR description LIKE ?)")
                sql_params.extend([search_val, search_val, search_val])
                
            if 'category' in params and params['category'][0]:
                conditions.append("category = ?")
                sql_params.append(params['category'][0])
                
            if 'min_price' in params and params['min_price'][0]:
                conditions.append("price >= ?")
                sql_params.append(float(params['min_price'][0]))
                
            if 'max_price' in params and params['max_price'][0]:
                conditions.append("price <= ?")
                sql_params.append(float(params['max_price'][0]))
                
            where_clause = ""
            if conditions:
                where_clause = "WHERE " + " AND ".join(conditions)
                
            # Total count
            cursor.execute(f"SELECT COUNT(*) as total FROM items {where_clause}", sql_params)
            total = cursor.fetchone()['total']
            
            # Select with pagination
            select_query = f"SELECT * FROM items {where_clause} ORDER BY id DESC LIMIT ? OFFSET ?"
            cursor.execute(select_query, sql_params + [limit, offset])
            rows = cursor.fetchall()
            items = [dict(row) for row in rows]
            
            # Categories
            cursor.execute("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")
            categories = [row['category'] for row in cursor.fetchall()]
            
            self.send_json({
                "status": "success",
                "data": items,
                "categories": categories,
                "pagination": {
                    "total": total,
                    "limit": limit,
                    "offset": offset,
                    "count": len(items)
                }
            })
        except Exception as e:
            self.send_json({"status": "error", "message": str(e)}, 500)
        finally:
            conn.close()

    def handle_api_post(self, input_data):
        if not input_data.get('name') or not input_data.get('sku') or input_data.get('price') is None or input_data.get('quantity') is None:
            self.send_json({"status": "error", "message": "Name, SKU, Price, and Quantity are required."}, 400)
            return
            
        name = input_data['name'].strip()
        sku = input_data['sku'].strip().upper()
        price = float(input_data['price'])
        quantity = int(input_data['quantity'])
        category = input_data.get('category', '').strip()
        description = input_data.get('description', '').strip()
        
        if price < 0 or quantity < 0:
            self.send_json({"status": "error", "message": "Price and Quantity must be positive values."}, 400)
            return
            
        conn = sqlite3.connect(DB_FILE)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        try:
            cursor.execute("SELECT id FROM items WHERE sku = ?", (sku,))
            if cursor.fetchone():
                self.send_json({"status": "error", "message": f"SKU '{sku}' already exists. SKU must be unique."}, 409)
                return
                
            cursor.execute("""
                INSERT INTO items (name, sku, description, price, quantity, category)
                VALUES (?, ?, ?, ?, ?, ?)
            """, (name, sku, description, price, quantity, category))
            conn.commit()
            
            new_id = cursor.lastrowid
            cursor.execute("SELECT * FROM items WHERE id = ?", (new_id,))
            new_item = dict(cursor.fetchone())
            
            self.send_json({
                "status": "success",
                "message": "Item created successfully.",
                "data": new_item
            }, 201)
        except Exception as e:
            self.send_json({"status": "error", "message": str(e)}, 500)
        finally:
            conn.close()

    def handle_api_put(self, item_id, input_data):
        conn = sqlite3.connect(DB_FILE)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        
        try:
            cursor.execute("SELECT * FROM items WHERE id = ?", (item_id,))
            existing = cursor.fetchone()
            if not existing:
                self.send_json({"status": "error", "message": "Item not found."}, 404)
                return
                
            existing = dict(existing)
            name = input_data.get('name', existing['name']).strip()
            sku = input_data.get('sku', existing['sku']).strip().upper()
            description = input_data.get('description', existing['description']).strip()
            price = float(input_data.get('price', existing['price']))
            quantity = int(input_data.get('quantity', existing['quantity']))
            category = input_data.get('category', existing['category']).strip()
            
            if not name or not sku:
                self.send_json({"status": "error", "message": "Name and SKU cannot be empty."}, 400)
                return
                
            if price < 0 or quantity < 0:
                self.send_json({"status": "error", "message": "Price and Quantity must be positive values."}, 400)
                return
                
            if sku != existing['sku']:
                cursor.execute("SELECT id FROM items WHERE sku = ? AND id != ?", (sku, item_id))
                if cursor.fetchone():
                    self.send_json({"status": "error", "message": f"SKU '{sku}' is already taken by another item."}, 409)
                    return
                    
            cursor.execute("""
                UPDATE items SET 
                name = ?, sku = ?, description = ?, price = ?, quantity = ?, category = ?, updated_at = datetime('now', 'localtime')
                WHERE id = ?
            """, (name, sku, description, price, quantity, category, item_id))
            conn.commit()
            
            cursor.execute("SELECT * FROM items WHERE id = ?", (item_id,))
            updated_item = dict(cursor.fetchone())
            
            self.send_json({
                "status": "success",
                "message": "Item updated successfully.",
                "data": updated_item
            })
        except Exception as e:
            self.send_json({"status": "error", "message": str(e)}, 500)
        finally:
            conn.close()

    def handle_api_delete(self, item_id):
        conn = sqlite3.connect(DB_FILE)
        cursor = conn.cursor()
        try:
            cursor.execute("SELECT id FROM items WHERE id = ?", (item_id,))
            if not cursor.fetchone():
                self.send_json({"status": "error", "message": "Item not found."}, 404)
                return
                
            cursor.execute("DELETE FROM items WHERE id = ?", (item_id,))
            conn.commit()
            self.send_json({"status": "success", "message": "Item deleted successfully."})
        except Exception as e:
            self.send_json({"status": "error", "message": str(e)}, 500)
        finally:
            conn.close()

    # --- HTTP Helpers ---

    def serve_static(self, filename, content_type):
        path = os.path.join(os.path.dirname(__file__), filename)
        if os.path.exists(path):
            self.send_response(200)
            self.send_header('Content-type', content_type)
            self.end_headers()
            with open(path, 'rb') as f:
                self.wfile.write(f.read())
        else:
            self.send_response(404)
            self.end_headers()

    def send_json(self, data, status_code=200):
        self.send_response(status_code)
        self.send_header('Content-type', 'application/json; charset=utf-8')
        self.end_headers()
        self.wfile.write(json.dumps(data).encode('utf-8'))

if __name__ == '__main__':
    init_db()
    print(f"\n[Vortex Server] Starting local sandbox server at http://localhost:{PORT}")
    print("Press Ctrl+C to terminate the server.\n")
    
    # Run the server
    handler = InventoryHandler
    with socketserver.TCPServer(("", PORT), handler) as httpd:
        try:
            httpd.serve_forever()
        except KeyboardInterrupt:
            print("\n[Vortex Server] Stopping server...")
            httpd.shutdown()
