# Jubert-Farms-Finance-Management-System
A comprehensive web-based finance management system designed for Jubert Farms to track income, expenses, generate financial reports, and manage transactions efficiently. Built to streamline financial operations and support better decision-making through real-time insights.




        .calendar-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .calendar-main {
            flex: 1;
            min-width: 700px;
        }
        
        .calendar-sidebar {
            width: 300px;
        }
        
        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .calendar-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .calendar-table th {
            background-color: #f4f4f4;
            padding: 10px;
            text-align: center;
            border: 1px solid #ddd;
        }
        
        .calendar-table td {
            height: 100px;
            vertical-align: top;
            border: 1px solid #ddd;
            padding: 5px;
        }
        
        .calendar-table td.inactive {
            background-color: #f9f9f9;
            color: #ccc;
        }
        
        .calendar-day {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .day-events {
            max-height: 85px;
            overflow-y: auto;
        }
        
        .event-item {
            margin-bottom: 5px;
            padding: 3px 5px;
            border-radius: 3px;
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
        
        .task-event {
            background-color: #e8f4f8;
            border-left: 3px solid #2196F3;
        }
        
        .harvest-event {
            background-color: #fff8e1;
            border-left: 3px solid #ff9800;
        }
        
        .sidebar-section {
            margin-bottom: 20px;
            background-color: #fff;
            border-radius: 5px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sidebar-section h3 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .event-list-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .event-list-item:last-child {
            border-bottom: none;
        }
        
        .event-date {
            font-size: 12px;
            color: #666;
        }
        
        .event-details {
            display: flex;
            align-items: center;
        }
        
        .event-icon {
            margin-right: 8px;
            width: 24px;
            text-align: center;
        }
        
        .event-title {
            flex: 1;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 5px;
            width: 60%;
            max-width: 600px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .modal-title {
            font-size: 18px;
            font-weight: bold;
            margin: 0;
        }
        
        .close-modal {
            font-size: 24px;
            cursor: pointer;
        }
        
        .modal-body {
            margin-bottom: 15px;
        }
        
        .event-property {
            margin-bottom: 10px;
        }
        
        .property-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .day-view-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
            font-weight: bold;
        }
        
        .btn-primary {
            background-color: #2196F3;
            color: white;
            border: none;
        }
        
        .btn-secondary {
            background-color: #f4f4f4;
            color: #333;
            border: 1px solid #ddd;
        }
        
        .btn-success {
            background-color: #4CAF50;
            color: white;
            border: none;
        }
        
        .btn-warning {
            background-color: #ff9800;
            color: white;
            border: none;
        }
        
        .btn-danger {
            background-color: #f44336;
            color: white;
            border: none;
        }
        
        .reminder-item {
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 3px;
            background-color: #f9f9f9;
        }