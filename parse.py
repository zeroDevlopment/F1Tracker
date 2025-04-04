import fastf1, sys, os, csv
from datetime import timedelta
import pandas as pd

workingPath = 'data/'+str(sys.argv[1])+'/'
#workingPath = 'data/australia/'
if not os.path.exists(workingPath):
    os.makedirs(workingPath)

session = fastf1.get_session(2025, sys.argv[1], sys.argv[2])
#session = fastf1.get_session(2025, 'australia', 'race')
session.load()

results = session.results
drivers = session.laps
#results.to_csv(workingPath+'results-race.csv', index=False)
results.to_csv(workingPath+'results-'+str(sys.argv[2])+'.csv', index=False)
#drivers.to_csv(workingPath+'lapSummary-race.csv', index=False)
drivers.to_csv(workingPath+'lapSummary-'+str(sys.argv[2])+'.csv', index=False)

baselineFinal = ''

def parseData():
    global workingPath
    driverData = []
    retiredDrivers = []
    driverLaps = {}
    #with open(workingPath+'results-race.csv', 'r') as file1:
    with open(workingPath+'results-'+str(sys.argv[2])+'.csv', 'r') as file1:
        reader = csv.reader(file1)
        next(reader)
        counter = 0
        for row in reader:
            counter+=1
            driverData.append({
                'name': row[2],
                'team': row[4],
                'position': row[13],
                'status': row[19],
                'points': row[20],
                'finalTime': computeFinalTime(formatFinalTime(row[18],counter)),
                'gap': formatFinalTime(row[18],counter)
            })

    for i in driverData:
        if i['status'] == 'Retired' or i['status'] == 'Disqualified':
            retiredDrivers.append(i['name'])

    #with open(workingPath+'lapSummary-race.csv', 'r') as file2:
    with open(workingPath+'lapSummary-'+str(sys.argv[2])+'.csv', 'r') as file2:
        reader = csv.reader(file2)
        next(reader)
        for row in reader:
            lapData = {
                'driver': row[1],
                'lapTime': formatTime(row[3]),
                'sector1': formatTime(row[8]),
                'sector2': formatTime(row[9]),
                'sector3': formatTime(row[10]),
                'speedTrap1': row[14],
                'speedTrap2': row[15],
                'speedTrapFinish': row[16],
                'compound': row[19],
                'position': row[26],
                'deleted': row[27]
            }

            if row[1] not in driverLaps and row[1] not in retiredDrivers:
                driverLaps[row[1]] = []
            if row[1] not in retiredDrivers:
                driverLaps[row[1]].append(lapData)

    analyzeInfo(driverLaps, driverData)

def formatTime(time):
    if time is '':
        return ''
    parts = time.split()
    timePart = parts[2]

    h, m, s = timePart.split(':')
    if '.' in s:
        sec, micro = s.split('.')
        formatted = f"{m.zfill(2)}:{sec.zfill(2)}.{micro[:3]}"
    else:
        formatted = f"{m.zfill(2)}:{s.zfill(2)}.000"

    return formatted

def computeFinalTime(time):
    global baselineFinal
    if time is '':
        return ''
    if time == baselineFinal:
        return time
    timeStr = time.split()[-1]

    h = 0
    m = 0

    if ':' in timeStr:
        m, s = timeStr.split(':')
        m = int(m)
        s = float(s)
    else:
        s = float(timeStr)

    bh, bm, bs = baselineFinal.split(':')
    bh = int(bh)
    bm = int(bm)
    bs = float(bs)

    newH = h + bh
    newM = m + bm
    newS = s + bs

    if newS >= 60:
        newM += 1
        newS -= 60
    if newM >= 60:
        newH += 1
        newM -= 60 

    s, ms = str(newS).split('.')
    
    newtime = f"{str(newH).zfill(2)}:{str(newM).zfill(2)}:{str(s).zfill(2)}.{str(ms)[:3]}"
    return newtime

def formatFromSeconds(time):
    mins = int(time//60)
    sec = time%60
    seconds = int(sec)
    ms = int(round((sec-seconds)*1000))
    formatted = f"{str(mins).zfill(2)}:{str(seconds).zfill(2)}.{str(ms).zfill(3)}"
    return formatted

def format2Seconds(time):
    if time is '':
        return ''
    mins, rest = time.split(':')
    secs, ms = rest.split('.')
    totalSeconds = int(mins)*60 + int(secs) + int(ms)/1000
    return totalSeconds

def formatFinalTime(time,counter):
    global baselineFinal
    if time is '':
        return ''
    if counter == 1:
        time_str = time.split()[-1]
        h, m, s = time_str.split(':')

        if '.' in s:
            sec, micro = s.split('.')
            formatted = f"{h.zfill(2)}:{m.zfill(2)}:{sec.zfill(2)}.{micro[:3]}"
        else:
            sec = s
            micro = '000'
            formatted = f"{h.zfill(2)}:{m.zfill(2)}:{sec.zfill(2)}.{micro}"
        baselineFinal = formatted
        return formatted
    else:
        time_str = time.split()[-1]
        h, m, s = time_str.split(':')
        if '.' in s:
            sec, micro = s.split('.')
            if m == '00':
                formatted = f"{sec.zfill(2)}.{micro[:3]}"
            if m != '00':
                formatted = f"{m.zfill(2)}:{sec.zfill(2)}.{micro[:3]}"
        else:
            sec = s
            micro = '000'
            if m == '00':
                formatted = f"{sec.zfill(2)}.{micro}"
            if m != '00':
                formatted = f"{m.zfill(2)}:{sec.zfill(2)}.{micro}"

        return formatted

def analyzeInfo(lapInfo, driverInfo):
    with open('data/races/'+str(sys.argv[1])+'-'+str(sys.argv[2])+'.csv', 'w') as file:
    #with open('data/races/test.csv', 'w') as file:
        writer = csv.writer(file)
        writer.writerow(['driver', 'team', 'position', 'status', 'points', 'fastestLap', 'fastestNum', 'slowestLap', 'slowestNum', 'finalTime', 'gap'])
        rowData = []
        for i in driverInfo:
            fastest = ''
            fastestLap = ''
            slowest = ''
            slowestLap = ''
            finalLap = i['finalTime']
            timeDiff = i['gap']
            lapCount = 0
            try:
                for j in lapInfo[i['name']]:
                    lapCount+=1
                    if fastest == '' or j['lapTime'] < fastest:
                        fastest = j['lapTime']
                        fastestLap = lapCount

                    if slowest == '' or j['lapTime'] > slowest:
                        slowest = j['lapTime']
                        slowestLap = lapCount

            except KeyError:
                fastest = 'DNF'
                fastestLap = 'DNF'
                slowest = 'DNF'
                slowestLap = 'DNF'
                finalLap = 'DNF'
                timeDiff = 'DNF'
            
            rowData.append([i['name'], i['team'], i['position'], i['status'], i['points'], fastest, fastestLap, slowest, slowestLap, finalLap, timeDiff])
        writer.writerows(rowData)
    file.close()
        

parseData()