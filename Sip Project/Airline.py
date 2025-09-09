import sys, os, csv
from PyQt5.QtWidgets import (
    QApplication, QMainWindow, QPushButton, QLabel, QVBoxLayout, QLineEdit,
    QMessageBox, QWidget, QStackedWidget, QFormLayout, QTableWidget, QTableWidgetItem
)
from PyQt5.QtGui import QFont
from PyQt5.QtCore import Qt


CSV_FILE = "booking_data.csv"
if not os.path.exists(CSV_FILE):
    with open(CSV_FILE, "w", newline="") as file:
        writer = csv.writer(file)
        writer.writerow(["Name", "Booking ID", "Class", "Seat", "Cost", "Destination", "Flight Date"])


BG_COLOR, TEXT_COLOR, BUTTON_COLOR = "#F5F5F5", "#E0B054", "#3A3A3A"
FONT_FAMILY = "Arial"
BUTTON_STYLE = f"""
    QPushButton {{
        background-color: {BUTTON_COLOR}; color: {TEXT_COLOR}; font-size: 16px;i 
        padding: 8px; border-radius: 6px; border: 1px solid {TEXT_COLOR};
    }}
    QPushButton:hover {{ background-color: #666666; }}
"""


class LoginPage(QWidget):
    def __init__(self, switch_page): 
        super().__init__()
        layout = QVBoxLayout()
        self.setStyleSheet(f"background-color: {BG_COLOR}; color: {TEXT_COLOR};")

        self.label = QLabel("🚪 Admin Login")
        self.label.setFont(QFont(FONT_FAMILY, 26))
        self.label.setAlignment(Qt.AlignCenter)

        self.username, self.password = QLineEdit(), QLineEdit()
        self.username.setPlaceholderText("Username")
        self.password.setPlaceholderText("Password")
        self.password.setEchoMode(QLineEdit.Password)

        self.login_button = QPushButton("Login")
        self.login_button.setStyleSheet(BUTTON_STYLE)
        self.login_button.clicked.connect(lambda: self.check_login(switch_page))

        layout.addWidget(self.label)
        layout.addWidget(self.username)
        layout.addWidget(self.password)
        layout.addWidget(self.login_button)
        self.setLayout(layout)

    def check_login(self, switch_page): 
        if self.username.text() == "vishal" and self.password.text() == "2318":
            switch_page(1)
        else:
            QMessageBox.warning(self, "Error", "Invalid Credentials.")


class AdminPage(QWidget):
    def __init__(self, switch_page):
        super().__init__()
        layout = QVBoxLayout()
        self.setStyleSheet(f"background-color: {BG_COLOR}; color: {TEXT_COLOR};")

        self.label = QLabel("✈️ Admin Dashboard")
        self.label.setFont(QFont(FONT_FAMILY, 22))
        self.label.setAlignment(Qt.AlignCenter)

        self.create_button = QPushButton("Create Booking")
        self.create_button.setStyleSheet(BUTTON_STYLE)
        self.create_button.clicked.connect(lambda: switch_page(2))

        self.manage_button = QPushButton("Manage Bookings")
        self.manage_button.setStyleSheet(BUTTON_STYLE)
        self.manage_button.clicked.connect(lambda: switch_page(3))

        self.logout_button = QPushButton("Logout")
        self.logout_button.setStyleSheet(BUTTON_STYLE)
        self.logout_button.clicked.connect(lambda: switch_page(0))

        layout.addWidget(self.label)
        layout.addWidget(self.create_button)
        layout.addWidget(self.manage_button)
        layout.addWidget(self.logout_button)
        self.setLayout(layout)


class CreateBooking(QWidget):
    def __init__(self, switch_page):
        super().__init__()
        layout = QFormLayout()
        self.setStyleSheet(f"background-color: {BG_COLOR}; color: {TEXT_COLOR};")

        self.inputs = {label: QLineEdit() for label in ["Name", "Booking ID", "Class", "Seat", "Cost", "Destination", "Flight Date"]}

        for label, widget in self.inputs.items():
            widget.setPlaceholderText(label)
            layout.addRow(label, widget)

        self.create_button = QPushButton("Create Booking")
        self.create_button.setStyleSheet(BUTTON_STYLE)
        self.create_button.clicked.connect(self.create_booking)

        self.back_button = QPushButton("⬅ Back")
        self.back_button.setStyleSheet(BUTTON_STYLE)
        self.back_button.clicked.connect(lambda: switch_page(1))

        layout.addWidget(self.create_button)
        layout.addWidget(self.back_button)
        self.setLayout(layout)

    def create_booking(self):
        data = [widget.text().strip() for widget in self.inputs.values()]
        
        if "" in data:
            QMessageBox.warning(self, "Error", "All fields must be filled!")
            return
        
        with open(CSV_FILE, "a", newline="") as file:
            writer = csv.writer(file)
            writer.writerow(data)

        QMessageBox.information(self, "Success", "Booking created successfully!")
        for widget in self.inputs.values():
            widget.clear()


class ManageBookings(QWidget):
    def __init__(self, switch_page):
        super().__init__()
        layout = QVBoxLayout()
        self.setStyleSheet(f"background-color: {BG_COLOR}; color: {TEXT_COLOR};")

        self.label = QLabel("🔍 Manage Bookings")
        self.label.setFont(QFont(FONT_FAMILY, 20))
        self.label.setAlignment(Qt.AlignCenter)

        self.search_input = QLineEdit()
        self.search_input.setPlaceholderText("Enter Booking ID")

        self.search_button = QPushButton("Search")
        self.search_button.setStyleSheet(BUTTON_STYLE)
        self.search_button.clicked.connect(self.search_booking)

        self.view_all_button = QPushButton("View All Bookings")
        self.view_all_button.setStyleSheet(BUTTON_STYLE)
        self.view_all_button.clicked.connect(self.view_all_bookings)

        self.table = QTableWidget()
        self.table.setColumnCount(7)
        self.table.setHorizontalHeaderLabels(["Name", "Booking ID", "Class", "Seat", "Cost", "Destination", "Flight Date"])
        self.table.setSelectionBehavior(QTableWidget.SelectRows)
        self.table.setSelectionMode(QTableWidget.SingleSelection)

        self.update_button = QPushButton("Update Booking")
        self.update_button.setStyleSheet(BUTTON_STYLE)
        self.update_button.clicked.connect(self.update_booking)

        self.delete_button = QPushButton("Delete Booking")
        self.delete_button.setStyleSheet(BUTTON_STYLE)
        self.delete_button.clicked.connect(self.delete_booking)

        self.sort_button = QPushButton("Sort by Cost")
        self.sort_button.setStyleSheet(BUTTON_STYLE)
        self.sort_button.clicked.connect(self.sort_bookings)

        self.export_button = QPushButton("Export to CSV")
        self.export_button.setStyleSheet(BUTTON_STYLE)
        self.export_button.clicked.connect(self.export_data)

        self.back_button = QPushButton("⬅ Back")
        self.back_button.setStyleSheet(BUTTON_STYLE)
        self.back_button.clicked.connect(lambda: switch_page(1))

        layout.addWidget(self.label)
        layout.addWidget(self.search_input)
        layout.addWidget(self.search_button)
        layout.addWidget(self.view_all_button)
        layout.addWidget(self.table)
        layout.addWidget(self.update_button)
        layout.addWidget(self.delete_button)
        layout.addWidget(self.sort_button)
        layout.addWidget(self.export_button)
        layout.addWidget(self.back_button)
        self.setLayout(layout)

    def search_booking(self):
        
        booking_id = self.search_input.text().strip()
        self.table.setRowCount(0)

        with open(CSV_FILE, "r") as file:
            reader = csv.reader(file)
            next(reader)
            for row in reader:
                if row[1] == booking_id:
                    row_position = self.table.rowCount()
                    self.table.insertRow(row_position)
                    for col, data in enumerate(row):
                        self.table.setItem(row_position, col, QTableWidgetItem(data))

    def view_all_bookings(self):
    
        self.table.setRowCount(0)

        with open(CSV_FILE, "r") as file:
            reader = csv.reader(file)
            next(reader)
            for row in reader:
                row_position = self.table.rowCount()
                self.table.insertRow(row_position)
                for col, data in enumerate(row):
                    self.table.setItem(row_position, col, QTableWidgetItem(data))

    def update_booking(self):
        
        selected_row = self.table.currentRow()
        if selected_row == -1:
            QMessageBox.warning(self, "Error", "Please select a booking to update.")
            return

        updated_data = []
        for col in range(self.table.columnCount()):
            item = self.table.item(selected_row, col)
            updated_data.append(item.text() if item else "")

        bookings = []
        with open(CSV_FILE, "r") as file:
            reader = csv.reader(file)
            headers = next(reader)
            for row in reader:
                if row[1] == updated_data[1]:
                    bookings.append(updated_data)
                else:
                    bookings.append(row)

        with open(CSV_FILE, "w", newline="") as file:
            writer = csv.writer(file)
            writer.writerow(headers)
            writer.writerows(bookings)

        QMessageBox.information(self, "Success", "Booking updated successfully!")

    def delete_booking(self):
        
        selected_row = self.table.currentRow()
        if selected_row == -1:
            QMessageBox.warning(self, "Error", "Please select a booking to delete.")
            return

        booking_id = self.table.item(selected_row, 1).text().strip()

        reply = QMessageBox.question(self, "Confirm Deletion", "Are you sure you want to delete this booking?",
                                     QMessageBox.Yes | QMessageBox.No, QMessageBox.No)

        if reply == QMessageBox.Yes:
            bookings = []
            with open(CSV_FILE, "r") as file:
                reader = csv.reader(file)
                headers = next(reader)
                bookings = [row for row in reader if row[1] != booking_id]

            with open(CSV_FILE, "w", newline="") as file:
                writer = csv.writer(file)
                writer.writerow(headers)
                writer.writerows(bookings)

            QMessageBox.information(self, "Success", "Booking deleted successfully!")
            self.table.removeRow(selected_row)

    def sort_bookings(self):
        
        bookings = []
        with open(CSV_FILE, "r") as file:
            reader = csv.reader(file)
            headers = next(reader)
            bookings = list(reader)

        bookings.sort(key=lambda x: float(x[4]))  

        self.table.setRowCount(0)
        for row in bookings:
            row_position = self.table.rowCount()
            self.table.insertRow(row_position)
            for col, data in enumerate(row):
                self.table.setItem(row_position, col, QTableWidgetItem(data))

    def export_data(self):
        
        with open("backup_bookings.csv", "w", newline="") as file:
            reader = csv.reader(open(CSV_FILE, "r"))
            writer = csv.writer(file)
            for row in reader:
                writer.writerow(row)

        QMessageBox.information(self, "Success", "Bookings exported to backup_bookings.csv!")



if __name__ == "__main__":
    app = QApplication(sys.argv)
    stack = QStackedWidget()

    stack.addWidget(LoginPage(stack.setCurrentIndex))
    stack.addWidget(AdminPage(stack.setCurrentIndex))
    stack.addWidget(CreateBooking(stack.setCurrentIndex))
    stack.addWidget(ManageBookings(stack.setCurrentIndex))

    main_window = QMainWindow()
    main_window.setCentralWidget(stack)
    main_window.setGeometry(400, 200, 800, 600)
    main_window.setWindowTitle("Flight Booking System")
    main_window.show()

    sys.exit(app.exec_())
