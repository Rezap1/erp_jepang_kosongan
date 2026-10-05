<?php

namespace App\Support\Migration;

final class WmsMigrationManifest
{
    /** @var array<string, array{0: string, 1: string, 2?: bool}> */
    private const DEFINITIONS = [
        'MASTER_ROLE' => ['Role_ID', 'Role_ID,Role_Name,Role_Description,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_MODULE' => ['Module_ID', 'Module_ID,Module_Name,Module_Code,Module_Group,Module_Order,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_DEPARTMENT' => ['Department_ID', 'Department_ID,Department_Name,Department_Code,Manager_Employee_ID,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_POSITION' => ['Position_ID', 'Position_ID,Position_Name,Position_Code,Department_ID,Position_Level,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_PROGRAM' => ['Program_ID', 'Program_ID,Program_Code,Program_Name,Program_Category,Description,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_BATCH' => ['Batch_ID', 'Batch_ID,Batch_Code,Batch_Name,Start_Date,End_Date,Program_ID,Batch_Status,Description,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_ACADEMIC_YEAR' => ['Academic_Year_ID', 'Academic_Year_ID,Name,Semester,Start_Date,End_Date,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_USER' => ['User_ID', 'User_ID,Username,Password,Full_Name,Email,Employee_ID,Role_ID,Is_Active,Last_Login,Failed_Login,Last_Password_Change,Created_At,Updated_At,Created_By,Updated_By,Notes,Phone_Number'],
        'MASTER_EMPLOYEE' => ['Employee_ID', 'Employee_ID,Employee_Number,Full_Name,Gender,Birth_Place,Birth_Date,National_ID,Phone_Number,Email,Address,Department_ID,Position_ID,Join_Date,Employment_Status,Tax_Number,Bank_Name,Bank_Account_Number,Account_Holder_Name,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes,User_ID'],
        'MASTER_TEACHER' => ['Teacher_ID', 'Teacher_ID,Employee_ID,Teacher_Code,Full_Name,Gender,Phone_Number,Email,Specialization,Hire_Date,Teaching_Status,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes,User_ID'],
        'MASTER_CLASS' => ['Class_ID', 'Class_ID,Class_Code,Class_Name,Batch_ID,Program_ID,Homeroom_Teacher_ID,Capacity,Current_Student,Class_Status,Description,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_STUDENT' => ['Student_ID', 'Student_ID,Student_Number,Registration_Date,Full_Name,Gender,Birth_Place,Birth_Date,National_ID,Phone_Number,Email,Address,Education,Program_ID,Class_ID,Batch_ID,Enrollment_Status,Graduation_Status,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes,User_ID'],
        'MASTER_COMPANY' => ['Company_ID', 'Company_ID,Company_Code,Company_Name,Legal_Name,NPWP,Business_License_Number,Address,City,Province,Postal_Code,Country,Phone_Number,Email,Website,Director_Name,Company_Logo,Company_Stamp,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_SUBJECT' => ['Subject_ID', 'Subject_ID,Subject_Code,Subject_Name,Description,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes,Program_ID,Category,Credit,Duration'],
        'MASTER_CLASS_ENROLLMENT' => ['Enrollment_ID', 'Enrollment_ID,Class_ID,Student_ID,Academic_Year_ID,Status,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_SCHEDULE' => ['Schedule_ID', 'Schedule_ID,Class_ID,Subject_ID,Teacher_ID,Academic_Year_ID,Day_Of_Week,Start_Time,End_Time,Room,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes'],
        'MASTER_ASSIGNMENT' => ['Assignment_ID', 'Assignment_ID,Class_ID,Subject_ID,Teacher_ID,Title,Description,Deadline,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes,Schedule_ID,Assignment_Type,Attachment,Publish_Date,Maximum_Score,Status'],
        'ASSESSMENTS' => ['Assessment_ID', 'Assessment_ID,Subject_ID,Class_ID,Title,Type,Max_Score,Date,Status,Created_At'],
        'MASTER_ASSESSMENT_CONFIG' => ['Category_ID', 'Category_ID,Category_Name,Aspects_JSON,Is_Active,Created_At,Updated_At'],
        'MASTER_SCORE' => ['Score_ID', 'Score_ID,Student_ID,Subject_ID,Academic_Year_ID,Exam_Type,Score,Grade,Feedback,Created_At,Updated_At,Created_By,Updated_By,Notes,Schedule_ID,Assignment_ID,Weight,Semester,Academic_Year,Remarks,Assessment_Category,Evaluation_Details'],
        'MASTER_ATTENDANCE' => ['Attendance_ID', 'Attendance_ID,Schedule_ID,Student_ID,Date,Status,Remarks,Created_At,Updated_At,Created_By,Updated_By,Notes,Teacher_ID,Attendance_Date,Check_In_Time,Check_Out_Time,Semester,Academic_Year,Session_Status,Grace_Period,Class_ID,Attendance_Type'],
        'MASTER_ATTENDANCE_REQUEST' => ['Request_ID', 'Request_ID,Attendance_ID,Student_ID,Schedule_ID,Attendance_Date,Request_Type,Reason,Evidence_URL,Status,Academic_Notes,Reviewed_By,Reviewed_At,Created_At,Updated_At'],
        'MASTER_ACCOUNT' => ['Account_ID', 'Account_ID,Account_Code,Account_Name,Account_Category,Parent_Account_ID,Description,Normal_Balance,Is_Active,Created_By,Created_At,Updated_At'],
        'FINANCE_INVOICE' => ['Invoice_ID', 'Invoice_ID,Student_ID,Period,Amount,Description,Status,Due_Date,Created_At,Updated_At,Invoice_Type,Company_ID,Category,Created_By,Updated_By,Line_Items,Is_Active'],
        'FINANCE_PAYMENT' => ['Payment_ID', 'Payment_ID,Invoice_ID,Student_ID,Amount_Paid,Payment_Date,Payment_Method,Reference_Number,Proof_Image,Status,Verified_By,Verified_At,Notes,Created_At,Updated_At,Created_By,Updated_By,Idempotency_Key,Idempotency_Fingerprint,Receipt_Number,Payment_Type,Is_Active'],
        'FINANCE_TRANSACTION' => ['Transaction_ID', 'Transaction_ID,Transaction_Date,Account_ID,Type,Category,Amount,Reference_Type,Reference_ID,Description,Is_Active,Created_By,Created_At,Updated_At,Updated_By'],
        'MASTER_SALARY_COMPONENT' => ['Component_ID', 'Component_ID,Type,Name,Amount,Is_Active,Created_At,Updated_At'],
        'MASTER_PAYROLL' => ['Payroll_ID', 'Payroll_ID,Employee_ID,Period,Base_Salary,Total_Allowances,Total_Deductions,Net_Salary,Status,Generated_At,Approved_At,Paid_At,Created_At,Approved_Date,Paid_Date,Payment_Proof,Notes,Generated_Document,Document_Number,Payroll_Period'],
        'MASTER_LEAVE' => ['Leave_ID', 'Leave_ID,Document_Number,Employee_ID,Employee_Name,Leave_Type,Start_Date,End_Date,Duration_Days,Reason,Status,Submitted_At,Approved_By,Approved_At,Rejected_By,Rejection_Reason,Rejected_At,Cancelled_By,Cancelled_At,Created_At,Updated_At'],
        'MASTER_OVERTIME' => ['Overtime_ID', 'Overtime_ID,Document_Number,Employee_ID,Employee_Name,Date,Start_Time,End_Time,Duration_Hours,Hourly_Rate,Overtime_Pay,Reason,Status,Submitted_At,Approved_By,Approved_At,Rejected_By,Rejection_Reason,Rejected_At,Created_At,Updated_At'],
        'MASTER_ANNOUNCEMENT' => ['Announcement_ID', 'Announcement_ID,Target_Role,Target_ID,Title,Content,Is_Active,Created_At,Updated_At,Created_By,Updated_By,Notes,Announcement_Type,Priority,Publish_Date,Expired_Date,Attachment'],
        'MASTER_NOTIFICATION' => ['Notification_ID', 'Notification_ID,User_ID,Title,Message,Is_Read,Link,Created_At,Updated_At,Reference_Type,Reference_ID'],
        'MASTER_WORKFLOW' => ['Workflow_ID', 'Workflow_ID,Module,Role,Step_Order,Is_Active'],
        'MASTER_APPROVAL' => ['Approval_ID', 'Approval_ID,Module,Reference_Type,Reference_ID,Current_Role,Status,Requester_ID,Created_At,Updated_At'],
        'MASTER_APPROVAL_HISTORY' => ['History_ID', 'History_ID,Approval_ID,Workflow_ID,Action,Old_Status,New_Status,Remarks,Acted_By,Created_At'],
        'MASTER_DOCUMENT' => ['Document_ID', 'Document_ID,Document_Number,Module,Reference_ID,Template_ID,Title,Content,Generated_By,Generated_Date,Status,QR_Code,Signature,Created_At,Updated_At'],
        'MASTER_PERMANENT_QR' => ['QR_ID', 'QR_ID,QR_TYPE,IDENTIFIER,LABEL,STATUS,CREATED_AT,CREATED_BY,UPDATED_AT,DEACTIVATED_AT,ACTIVE_FROM,ACTIVE_UNTIL,UPDATED_BY'],
        'MASTER_SYSTEM_SETTING' => ['Setting_ID', 'Setting_ID,Category,Setting_Key,Setting_Name,Setting_Value,Value_Type,Description,Is_Public,Status,Created_By,Updated_By,Created_At,Updated_At', true],
        'MASTER_SYSTEM_PARAMETER' => ['Parameter_ID', 'Parameter_ID,Module,Parameter_Key,Parameter_Value,Description,Status,Created_At,Updated_At'],
        'MASTER_ALUMNI' => ['Alumni_ID', 'Alumni_ID,Source_Type,Student_ID,Full_Name,NIK,Parent_Name,Indonesia_Address,Visa_Number,Visa_Type,Japan_City,Departure_Date,Photo,Program_ID,Pathway,Work_Field,Batch_ID,Class_ID,Status,Is_Active,Created_At,Updated_At,Created_By,Updated_By'],
        'QUIZ' => ['Quiz_ID', 'Quiz_ID,Teacher_ID,Class_ID,Title,Start_At,End_At,Duration_Minutes,Status,Created_At,Updated_At,Created_By,Updated_By'],
        'QUIZ_QUESTION' => ['Question_ID', 'Question_ID,Quiz_ID,Question_Text,Option_A,Option_B,Option_C,Option_D,Correct_Option,Point,Sort_Order,Created_At,Updated_At'],
        'QUIZ_ATTEMPT' => ['Attempt_ID', 'Attempt_ID,Quiz_ID,Student_ID,Started_At,Deadline_At,Status,Submitted_Answers_JSON,Submitted_At,Created_At,Updated_At'],
        'QUIZ_RESULT' => ['Result_ID', 'Result_ID,Quiz_ID,Quiz_Title,Attempt_ID,Student_ID,Student_Name,Class_ID,Teacher_ID,Raw_Score,Maximum_Score,Normalized_Score,Started_At,Completed_At,Created_At'],
        'MASTER_AUDIT_LOG' => ['Audit_ID', 'Audit_ID,User_ID,Role,Department,Module,Reference_Type,Reference_ID,Action,Old_Value,New_Value,IPAddress,Device,Browser,Operating_System,Location,Status,Created_At'],
    ];

    /** @return array<string, array{sheet: string, table: string, primary_key: string, headers: list<string>, preserve_duplicate_ids: bool}> */
    public static function contracts(): array
    {
        $contracts = [];

        foreach (self::DEFINITIONS as $sheet => $definition) {
            $contracts[$sheet] = [
                'sheet' => $sheet,
                'table' => $sheet,
                'primary_key' => $definition[0],
                'headers' => explode(',', $definition[1]),
                'preserve_duplicate_ids' => $definition[2] ?? false,
            ];
        }

        return $contracts;
    }

    public static function columnType(string $column): string
    {
        if ($column === 'Work_Field') {
            return 'work_field';
        }
        if (in_array($column, self::decimalColumns(), true)) {
            return 'decimal';
        }
        if (in_array($column, self::integerColumns(), true)) {
            return 'integer';
        }
        if ($column === 'Is_Read' || str_starts_with($column, 'Is_')) {
            return 'boolean';
        }
        if (in_array($column, ['Start_Time', 'End_Time', 'Check_In_Time', 'Check_Out_Time'], true)) {
            return 'time';
        }
        if (in_array($column, ['Publish_Date', 'Expired_Date'], true)) {
            return 'datetime';
        }
        if ($column === 'Date' || str_ends_with($column, '_Date')) {
            return 'date';
        }
        if (str_ends_with($column, '_At') || str_ends_with($column, '_AT') || in_array($column, ['Deadline', 'Last_Login', 'Last_Password_Change', 'ACTIVE_FROM', 'ACTIVE_UNTIL'], true)) {
            return 'datetime';
        }
        if (in_array($column, self::jsonCompatibleColumns(), true)) {
            return 'json_text';
        }
        if (str_ends_with($column, '_ID')) {
            return 'identifier';
        }
        if (preg_match('/(?:Status|Type|Code|Number|Key|Email|Phone|Gender|Role|Category|Semester|Period|Priority|Grade|Method|IDENTIFIER|LABEL|STATUS)$/i', $column)) {
            return 'short_string';
        }

        return 'text';
    }

    /** @return list<string> */
    public static function decimalColumns(): array
    {
        return ['Amount', 'Amount_Paid', 'Base_Salary', 'Total_Allowances', 'Total_Deductions', 'Net_Salary', 'Hourly_Rate', 'Overtime_Pay', 'Max_Score', 'Maximum_Score', 'Raw_Score', 'Normalized_Score', 'Point', 'Score', 'Weight', 'Duration_Hours'];
    }

    /** @return list<string> */
    public static function integerColumns(): array
    {
        return ['Capacity', 'Current_Student', 'Credit', 'Duration', 'Duration_Days', 'Duration_Minutes', 'Failed_Login', 'Grace_Period', 'Module_Order', 'Sort_Order', 'Step_Order'];
    }

    /** @return list<string> */
    public static function jsonCompatibleColumns(): array
    {
        return ['Line_Items', 'Aspects_JSON', 'Submitted_Answers_JSON', 'Evaluation_Details', 'Old_Value', 'New_Value'];
    }

    /** @return list<string> */
    public static function indexedColumns(array $contract): array
    {
        return array_values(array_filter($contract['headers'], static function (string $column) use ($contract): bool {
            if ($column === $contract['primary_key']) {
                return false;
            }

            return str_ends_with($column, '_ID') || $column === 'Status' || str_ends_with($column, '_Status')
                || in_array($column, ['Date', 'Attendance_Date', 'Payment_Date', 'Transaction_Date', 'Due_Date', 'Created_At'], true);
        }));
    }

    /** @return list<array{child: string, column: string, parent: string, parent_key: string}> */
    public static function relationships(): array
    {
        $definitions = [
            'MASTER_POSITION.Department_ID>MASTER_DEPARTMENT.Department_ID',
            'MASTER_BATCH.Program_ID>MASTER_PROGRAM.Program_ID',
            'MASTER_USER.Role_ID>MASTER_ROLE.Role_ID',
            'MASTER_EMPLOYEE.Department_ID>MASTER_DEPARTMENT.Department_ID',
            'MASTER_EMPLOYEE.Position_ID>MASTER_POSITION.Position_ID',
            'MASTER_EMPLOYEE.User_ID>MASTER_USER.User_ID',
            'MASTER_TEACHER.Employee_ID>MASTER_EMPLOYEE.Employee_ID',
            'MASTER_TEACHER.User_ID>MASTER_USER.User_ID',
            'MASTER_CLASS.Batch_ID>MASTER_BATCH.Batch_ID',
            'MASTER_CLASS.Program_ID>MASTER_PROGRAM.Program_ID',
            'MASTER_CLASS.Homeroom_Teacher_ID>MASTER_TEACHER.Teacher_ID',
            'MASTER_STUDENT.User_ID>MASTER_USER.User_ID',
            'MASTER_STUDENT.Program_ID>MASTER_PROGRAM.Program_ID',
            'MASTER_STUDENT.Class_ID>MASTER_CLASS.Class_ID',
            'MASTER_STUDENT.Batch_ID>MASTER_BATCH.Batch_ID',
            'MASTER_SUBJECT.Program_ID>MASTER_PROGRAM.Program_ID',
            'MASTER_CLASS_ENROLLMENT.Class_ID>MASTER_CLASS.Class_ID',
            'MASTER_CLASS_ENROLLMENT.Student_ID>MASTER_STUDENT.Student_ID',
            'MASTER_CLASS_ENROLLMENT.Academic_Year_ID>MASTER_ACADEMIC_YEAR.Academic_Year_ID',
            'MASTER_SCHEDULE.Class_ID>MASTER_CLASS.Class_ID',
            'MASTER_SCHEDULE.Subject_ID>MASTER_SUBJECT.Subject_ID',
            'MASTER_SCHEDULE.Teacher_ID>MASTER_TEACHER.Teacher_ID',
            'MASTER_ATTENDANCE.Schedule_ID>MASTER_SCHEDULE.Schedule_ID',
            'MASTER_ATTENDANCE.Student_ID>MASTER_STUDENT.Student_ID',
            'MASTER_ATTENDANCE.Teacher_ID>MASTER_TEACHER.Teacher_ID',
            'MASTER_ATTENDANCE.Class_ID>MASTER_CLASS.Class_ID',
            'MASTER_ATTENDANCE_REQUEST.Attendance_ID>MASTER_ATTENDANCE.Attendance_ID',
            'MASTER_ATTENDANCE_REQUEST.Student_ID>MASTER_STUDENT.Student_ID',
            'MASTER_SCORE.Student_ID>MASTER_STUDENT.Student_ID',
            'MASTER_SCORE.Subject_ID>MASTER_SUBJECT.Subject_ID',
            'MASTER_SCORE.Schedule_ID>MASTER_SCHEDULE.Schedule_ID',
            'MASTER_SCORE.Assignment_ID>MASTER_ASSIGNMENT.Assignment_ID',
            'MASTER_ASSIGNMENT.Class_ID>MASTER_CLASS.Class_ID',
            'MASTER_ASSIGNMENT.Subject_ID>MASTER_SUBJECT.Subject_ID',
            'MASTER_ASSIGNMENT.Teacher_ID>MASTER_TEACHER.Teacher_ID',
            'FINANCE_INVOICE.Student_ID>MASTER_STUDENT.Student_ID',
            'FINANCE_INVOICE.Company_ID>MASTER_COMPANY.Company_ID',
            'FINANCE_PAYMENT.Invoice_ID>FINANCE_INVOICE.Invoice_ID',
            'FINANCE_PAYMENT.Student_ID>MASTER_STUDENT.Student_ID',
            'FINANCE_TRANSACTION.Account_ID>MASTER_ACCOUNT.Account_ID',
            'MASTER_PAYROLL.Employee_ID>MASTER_EMPLOYEE.Employee_ID',
            'MASTER_LEAVE.Employee_ID>MASTER_EMPLOYEE.Employee_ID',
            'MASTER_OVERTIME.Employee_ID>MASTER_EMPLOYEE.Employee_ID',
            'MASTER_NOTIFICATION.User_ID>MASTER_USER.User_ID',
            'MASTER_APPROVAL_HISTORY.Approval_ID>MASTER_APPROVAL.Approval_ID',
            'MASTER_APPROVAL_HISTORY.Workflow_ID>MASTER_WORKFLOW.Workflow_ID',
            'MASTER_ALUMNI.Student_ID>MASTER_STUDENT.Student_ID',
            'QUIZ.Teacher_ID>MASTER_TEACHER.Teacher_ID',
            'QUIZ.Class_ID>MASTER_CLASS.Class_ID',
            'QUIZ_QUESTION.Quiz_ID>QUIZ.Quiz_ID',
            'QUIZ_ATTEMPT.Quiz_ID>QUIZ.Quiz_ID',
            'QUIZ_ATTEMPT.Student_ID>MASTER_STUDENT.Student_ID',
            'QUIZ_RESULT.Quiz_ID>QUIZ.Quiz_ID',
            'QUIZ_RESULT.Attempt_ID>QUIZ_ATTEMPT.Attempt_ID',
            'QUIZ_RESULT.Student_ID>MASTER_STUDENT.Student_ID',
        ];

        return array_map(static function (string $definition): array {
            [$child, $parent] = explode('>', $definition);
            [$childTable, $column] = explode('.', $child);
            [$parentTable, $parentKey] = explode('.', $parent);

            return ['child' => $childTable, 'column' => $column, 'parent' => $parentTable, 'parent_key' => $parentKey];
        }, $definitions);
    }

    /** @return list<string> */
    public static function excludedDocumentationSheets(): array
    {
        return ['README', 'SYSTEM_INFO', 'MODULES', 'DATA_DICTIONARY', 'DATABASE_STANDARD', 'ID_STANDARD', 'DATABASE_RELATIONSHIP', 'MASTER_DATABASE_ROADMAP', 'WMS_DEVELOPMENT_STANDARD'];
    }

    /** @return list<string> */
    public static function inactiveLegacySheets(): array
    {
        return ['INTERVIEW', 'MASTER_PERMISSION', 'JOB_ORDER', 'MATCHING', 'APPLICATION', 'DOCUMENT', 'COE', 'VISA', 'MASTER_SUBMISSION', 'MASTER_DOCUMENT_TEMPLATE', 'MASTER_LETTERHEAD_SETTING'];
    }
}
