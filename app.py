from flask import Flask, request, jsonify, render_template, redirect, url_for
from flask_sqlalchemy import SQLAlchemy
from datetime import datetime, timedelta
import os

app = Flask(__name__)

# Database Configuration (SQLite)
# Render-এ হোস্ট করলে ডিস্ক মাউন্ট করার জন্য '/data/macro.db' ব্যবহার করা ভালো
db_path = os.path.join(os.getcwd(), 'macro_users.db')
app.config['SQLALCHEMY_DATABASE_URI'] = 'sqlite:///' + db_path
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False
app.config['SECRET_KEY'] = 'shrabon_gomez_2026_key'

db = SQLAlchemy(app)

# Database Model
class User(db.Model):
    id = db.Column(db.Integer, primary_key=True)
    device_id = db.Column(db.String(100), unique=True, nullable=False)
    model = db.Column(db.String(100))
    expiry_date = db.Column(db.DateTime, nullable=False)
    plan_type = db.Column(db.String(50), default="Premium") # Premium or Trial

# Create database tables
with app.app_context():
    db.create_all()

# --- API FOR ANDROID APP ---

@app.route('/api/check_license', methods=['GET'])
def check_license():
    dev_id = request.args.get('device_id')
    user = User.query.filter_by(device_id=dev_id).first()
    
    if user:
        if datetime.now() < user.expiry_date:
            return jsonify({
                "status": "active",
                "plan": user.plan_type,
                "expiry": user.expiry_date.strftime("%Y-%m-%d %H:%M:%S")
            })
    return jsonify({"status": "expired"})

@app.route('/api/request_trial', methods=['POST'])
def request_trial():
    dev_id = request.form.get('device_id')
    model = request.form.get('model')
    
    # Check if device already exists in DB
    existing_user = User.query.filter_by(device_id=dev_id).first()
    if existing_user:
        return jsonify({"status": "error", "message": "Trial already used!"})
    
    # Grant 24 Hours Trial
    expiry = datetime.now() + timedelta(hours=24)
    new_trial = User(device_id=dev_id, model=model, expiry_date=expiry, plan_type="Free Trial")
    db.session.add(new_trial)
    db.session.commit()
    return jsonify({"status": "success", "expiry": expiry.strftime("%Y-%m-%d %H:%M:%S")})

# --- ADMIN PANEL UI ---

@app.route('/admin')
def admin_panel():
    users = User.query.order_by(User.id.desc()).all()
    return render_template('admin.html', users=users)

@app.route('/admin/activate', methods=['POST'])
def activate_user():
    dev_id = request.form.get('device_id')
    days = int(request.form.get('days'))
    
    expiry = datetime.now() + timedelta(days=days)
    user = User.query.filter_by(device_id=dev_id).first()
    
    if user:
        user.expiry_date = expiry
        user.plan_type = "Premium"
    else:
        new_user = User(device_id=dev_id, expiry_date=expiry, plan_type="Premium")
        db.session.add(new_user)
    
    db.session.commit()
    return redirect(url_for('admin_panel'))

@app.route('/admin/delete/<int:id>')
def delete_user(id):
    user = User.query.get(id)
    if user:
        db.session.delete(user)
        db.session.commit()
    return redirect(url_for('admin_panel'))

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)