import psycopg2

try:
    conn = psycopg2.connect(
        host="localhost",
        database="trustlink",
        user="postgres",
        password="Nasiuma.12?"
    )

    print("Connected successfully!")

    cur = conn.cursor()

    cur.execute("SELECT NOW();")
    result = cur.fetchone()

    print("Current time:", result)

    cur.close()
    conn.close()

except Exception as e:
    print("Error:", e)