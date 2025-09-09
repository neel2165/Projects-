from flask import Flask, render_template, request, redirect, url_for, session
import ast  

app = Flask(__name__)
app.secret_key = "your_secret_key_here"

USERNAME = "vishal"
PASSWORD = "gyds@776"


def load_students():
   
    students = []
    try:
        with open("students.txt", "r") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                try:
                    student = ast.literal_eval(line)
                    students.append(student)
                except (ValueError, SyntaxError):
                    print(f"Skipping invalid line: {line}")
    except FileNotFoundError:
        print("students.txt not found")
    return students


@app.route('/', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        user = request.form['username']
        pwd = request.form['password']
        if user == USERNAME and pwd == PASSWORD:
            session['logged_in'] = True
            return redirect(url_for('dashboard'))
        else:
            return render_template("login.html", error="Invalid username or password")
    return render_template("login.html")


@app.route('/dashboard')
def dashboard():
    if not session.get('logged_in'):
        return redirect(url_for('login'))

    students = load_students()

 
    attendance_labels = ['DM', 'SE', 'IS', 'ASP.NET', 'PHP']
    attendance_values = []
    for subject in attendance_labels:
        total = count = 0
        for s in students:
            if subject in s.get('attendance', {}):
                total += s['attendance'][subject]
                count += 1
        average = total // count if count > 0 else 0
        attendance_values.append(average)

    
    exams = [
        {'subject': 'DM', 'date': '2025-07-15'},
        {'subject': 'SE', 'date': '2025-07-20'},
        {'subject': 'ASP.NET', 'date': '2025-07-25'}
    ]

 
    faculties = [
        {'name': 'Prof. Vishal Parekh', 'subject': 'PHP'},
        {'name': 'Prof. Neel Gardhariya', 'subject': 'SE'},
        {'name': 'Prof. Heet Bhimani', 'subject': 'ASP.NET'}
    ]

    return render_template('dashboard.html',
                           students=students,
                           attendance_labels=attendance_labels,
                           attendance_values=attendance_values,
                           exams=exams,
                           faculties=faculties)


@app.route('/results')
def results():
    if not session.get('logged_in'):
        return redirect(url_for('login'))

    students = load_students()
    return render_template('result.html', students=students)


@app.route('/logout')
def logout():
    session.clear()
    return redirect(url_for('login'))


if __name__ == '__main__':
    app.run(debug=True)
