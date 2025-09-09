import tkinter as tk
from tkinter import ttk, messagebox, filedialog
import csv, shutil
from datetime import datetime
from tkcalendar import DateEntry
import matplotlib.pyplot as plt
from collections import Counter

FILE_NAME = "railway_bookings.csv"
BACKUP_FILE = "railway_bookings_backup.csv"

HEADERS = ["Mobile","Name","Departure","Arrival","Train Type","Class","Email","Meal","Travel Date"]

def initialize_csv():
    try:
        with open(FILE_NAME,"r") as f:
            if next(csv.reader(f)) != HEADERS:
                raise ValueError
    except:
        with open(FILE_NAME,"w",newline="") as f:
            csv.writer(f).writerow(HEADERS)

def read_all_rows():
    try:
        with open(FILE_NAME,"r") as f:
            rows=list(csv.reader(f))
        return rows[0],rows[1:]
    except FileNotFoundError:
        return HEADERS,[]

def save_rows(rows):
    with open(FILE_NAME,"w",newline="") as f:
        csv.writer(f).writerows(rows)

def load_bookings(filter_text=""):
    for row in tree.get_children():
        tree.delete(row)
    _,rows=read_all_rows()
    for r in rows:
        if filter_text.lower() in " ".join(r).lower():
            tree.insert("",tk.END,values=r)

def save_booking():
    data=[mobile_var.get(),name_var.get(),dep_var.get(),arr_var.get(),
          type_var.get(),class_var.get(),email_var.get(),meal_var.get(),date_var.get()]

    if not data[0].isdigit() or len(data[0])!=10:
        messagebox.showerror("Error","Invalid Mobile Number")
        return
    if "@" not in data[6]:
        messagebox.showerror("Error","Invalid Email")
        return
    try:
        entered=datetime.strptime(data[8],"%Y-%m-%d")
        if entered.date()<datetime.now().date():
            messagebox.showerror("Error","Past date not allowed")
            return
    except:
        messagebox.showerror("Error","Invalid Date")
        return

    headers,rows=read_all_rows()
    if edit_mode.get()==0: # new
        for r in rows:
            if r[0]==data[0]:
                messagebox.showerror("Error","Mobile already exists")
                return
        rows.append(data)
    else: # edit
        for i,r in enumerate(rows):
            if r[0]==data[0]:
                rows[i]=data
                break
        edit_mode.set(0)
        save_btn.config(text="Save Booking")

    save_rows([headers]+rows)
    messagebox.showinfo("Success","Booking saved!")
    clear_form(); load_bookings()

def clear_form():
    for var in [mobile_var,name_var,dep_var,arr_var,email_var]:
        var.set("")
    type_var.set("Express"); class_var.set("AC"); meal_var.set("Veg")
    date_var.set(datetime.now().strftime("%Y-%m-%d"))

def on_row_select(event):
    item=tree.selection()
    if not item: return
    values=tree.item(item,"values")
    mobile_var.set(values[0]); name_var.set(values[1]); dep_var.set(values[2]); arr_var.set(values[3])
    type_var.set(values[4]); class_var.set(values[5]); email_var.set(values[6]); meal_var.set(values[7])
    date_var.set(values[8])
    edit_mode.set(1); save_btn.config(text="Update Booking")

def delete_booking():
    item=tree.selection()
    if not item: return
    values=tree.item(item,"values")
    mobile=values[0]
    headers,rows=read_all_rows()
    rows=[r for r in rows if r[0]!=mobile]
    save_rows([headers]+rows)
    messagebox.showinfo("Deleted","Booking deleted!")
    load_bookings()

def backup(): shutil.copy(FILE_NAME,BACKUP_FILE); messagebox.showinfo("Backup","Done!")
def restore(): shutil.copy(BACKUP_FILE,FILE_NAME); messagebox.showinfo("Restore","Done!"); load_bookings()

def export_excel():
    from openpyxl import Workbook
    headers,rows=read_all_rows()
    wb=Workbook(); ws=wb.active
    ws.append(headers)
    for r in rows: ws.append(r)
    f=filedialog.asksaveasfilename(defaultextension=".xlsx")
    if f: wb.save(f); messagebox.showinfo("Export","Exported to Excel!")

def show_stats():
    _,rows=read_all_rows()
    if not rows: messagebox.showinfo("Stats","No data"); return
    departures=[r[2] for r in rows]
    counter=Counter(departures)
    plt.bar(counter.keys(),counter.values())
    plt.title("Most Popular Departure Stations")
    plt.show()

root=tk.Tk(); root.title("Advanced Railway Booking"); root.geometry("1100x650")

# --- Variables ---
mobile_var=tk.StringVar(); name_var=tk.StringVar(); dep_var=tk.StringVar(); arr_var=tk.StringVar()
type_var=tk.StringVar(value="Express"); class_var=tk.StringVar(value="AC")
email_var=tk.StringVar(); meal_var=tk.StringVar(value="Veg")
date_var=tk.StringVar(value=datetime.now().strftime("%Y-%m-%d"))
edit_mode=tk.IntVar(value=0)

# --- Search bar ---
search_var=tk.StringVar()
tk.Label(root,text="Search:").pack(anchor="w")
tk.Entry(root,textvariable=search_var).pack(fill="x")
def on_search(*_): load_bookings(search_var.get())
search_var.trace("w",on_search)

# --- Form ---
form=tk.Frame(root,pady=5); form.pack(fill="x")
labels=["Mobile","Name","Departure","Arrival","Email"]
vars=[mobile_var,name_var,dep_var,arr_var,email_var]
for i,(lab,var) in enumerate(zip(labels,vars)):
    tk.Label(form,text=lab).grid(row=i,column=0,sticky="w")
    tk.Entry(form,textvariable=var).grid(row=i,column=1)

ttk.Label(form,text="Train Type").grid(row=0,column=2)
ttk.Combobox(form,textvariable=type_var,values=["Express","Local"]).grid(row=0,column=3)

ttk.Label(form,text="Class").grid(row=1,column=2)
ttk.Combobox(form,textvariable=class_var,values=["AC","Sleeper"]).grid(row=1,column=3)

ttk.Label(form,text="Meal").grid(row=2,column=2)
ttk.Combobox(form,textvariable=meal_var,values=["Veg","Non-Veg"]).grid(row=2,column=3)

ttk.Label(form,text="Travel Date").grid(row=3,column=2)
DateEntry(form,textvariable=date_var,date_pattern="yyyy-mm-dd").grid(row=3,column=3)

save_btn=tk.Button(form,text="Save Booking",command=save_booking,bg="green",fg="white")
save_btn.grid(row=6,column=0,pady=10)

tk.Button(form,text="Delete",command=delete_booking,bg="red",fg="white").grid(row=6,column=1)
tk.Button(form,text="Backup",command=backup).grid(row=6,column=2)
tk.Button(form,text="Restore",command=restore).grid(row=6,column=3)
tk.Button(form,text="Export Excel",command=export_excel).grid(row=6,column=4)
tk.Button(form,text="Show Stats",command=show_stats).grid(row=6,column=5)
tk.Button(form,text="Clear",command=clear_form).grid(row=6,column=6)

# --- Table ---
tree=ttk.Treeview(root,columns=HEADERS,show="headings",height=15)
tree.pack(fill="both",expand=True)
for h in HEADERS:
    tree.heading(h,text=h,command=lambda c=h: sort_by(c,False))
    tree.column(h,width=120)
def sort_by(col,descending):
    data=[(tree.set(child,col),child) for child in tree.get_children("")]
    data.sort(reverse=descending)
    for index,(val,child) in enumerate(data): tree.move(child,"",index)
    tree.heading(col,command=lambda: sort_by(col,not descending))
tree.bind("<Double-1>",on_row_select)

initialize_csv(); load_bookings()
root.mainloop()
