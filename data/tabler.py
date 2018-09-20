import re

SAMPLE="""RK PLAYER PASS EPA RUN EPA SACK EPA PEN EPA TOTAL EPA ACT PLAYS RAW QBR TOTAL QBR
1 Justin Herbert, ORE 12.5 2.1 -0.5 0.1 14.2 60 95.3 89.1
2 K.J. Costello, STAN 9.5 0.0 -0.6 0.5 9.5 69 77.2 82.5
3 Steven Montez, COLO 16.3 4.1 -4.8 -0.1 15.5 96 79.2 76.9
4 Chase Garbers, CAL 3.5 4.6 -1.6 1.7 8.2 62 75.4 76.0
5 Conor Blount, ORST 7.1 -0.2 -3.3 1.2 4.8 55 71.3 70.6
6 Manny Wilkins, ASU 11.1 -0.9 -1.4 0.6 9.5 94 67.4 70.3
7 Jake Browning, WASH 10.9 1.3 -4.6 -0.4 7.3 93 60.7 63.1
8 Gardner Minshew, WSU 9.5 1.5 0.0 0.8 11.8 129 68.4 58.3
9 Khalil Tate, ARIZ 3.7 -0.5 -1.1 2.3 4.4 106 53.5 53.2
10 JT Daniels, USC 4.1 0.7 -4.3 1.0 1.6 86 43.1 41.5
11 Tyler Huntley, UTAH 8.3 -2.7 -4.6 0.7 1.7 105 42.5 34.4
12 Dorian Thompson-Robinson, UCLA 1.2 0.3 -7.6 -0.1 -6.2 69 13.8 20.3"""


class Tabler(object):
    ROW_RE = re.compile("^([0-9]+) ([^,]+)[,] [A-Z]+ ([-.0-9]+) ([-.0-9]+) ([-.0-9]+) ([-.0-9]+) ([-.0-9]+) ([-.0-9]+) ([-.0-9]+) ([-.0-9]+)$")
    def __init__(self, data):
        self.header = data[0].split(" ")
        self.data = self.process_rows(data[1:])

    def process_rows(self, rows):
        data = []
        for row in rows:
            match = self.ROW_RE.match(row.strip())
            if match is None:
                print "Unable to match: %s" % row.strip()
            else:
                data.append(match.groups())
        return data

    def get_table_code(self):
        tokens = ["[table]\n"]
        tokens.append("[tr]")
        for column in self.header:
            tokens.append("[th]%s[/th]" % column)
        tokens.append("[/tr]\n")
        for row in self.data:
            tokens.append("[tr]")
            for value in row:
                tokens.append("[td]%s[/td]" % value)
            tokens.append("[/tr]\n")
        tokens.append("[/table]")
        return "".join(tokens)



data = SAMPLE.split("\n")
tabler = Tabler(data)
print tabler.get_table_code()