<?php
// local/orgadmin/simple_assessment.php - Simple Assessment Interface

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/role_detector.php');

require_login();

// Only students should access this
if (!orgadmin_role_detector::should_show_student_dashboard()) {
    redirect(new moodle_url('/my/index.php'));
}

$PAGE->set_url(new moodle_url('/local/orgadmin/simple_assessment.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('Assessment Interface');
$PAGE->set_heading('Student Assessment');

echo $OUTPUT->header();
?>

<style>
.assessment-container {
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.question {
    background: #f8f9fa;
    padding: 20px;
    margin: 15px 0;
    border-radius: 6px;
    border-left: 4px solid #007bff;
}

.question h3 {
    margin-top: 0;
    color: #333;
}

.options {
    margin: 15px 0;
}

.option {
    margin: 10px 0;
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.option:hover {
    background: #e9ecef;
}

.option input[type="radio"] {
    margin-right: 10px;
}

.submit-btn {
    background: #28a745;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 20px;
}

.submit-btn:hover {
    background: #218838;
}

.result {
    margin-top: 20px;
    padding: 15px;
    border-radius: 6px;
    display: none;
}

.result.success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.back-btn {
    background: #6c757d;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    text-decoration: none;
    display: inline-block;
    margin-top: 10px;
}

.back-btn:hover {
    background: #5a6268;
    color: white;
    text-decoration: none;
}
</style>

<div class="assessment-container">
    <h2>Programming Assessment</h2>
    <p>Welcome <?php echo fullname($USER); ?>! Complete the following questions:</p>

    <form id="assessmentForm">
        <div class="question">
            <h3>Question 1: What is the correct syntax for a Java main method?</h3>
            <div class="options">
                <label class="option">
                    <input type="radio" name="q1" value="a">
                    public static void main(String args[])
                </label>
                <label class="option">
                    <input type="radio" name="q1" value="b">
                    public void main(String[] args)
                </label>
                <label class="option">
                    <input type="radio" name="q1" value="c">
                    static void main(String[] args)
                </label>
                <label class="option">
                    <input type="radio" name="q1" value="d">
                    public static main(String[] args)
                </label>
            </div>
        </div>

        <div class="question">
            <h3>Question 2: Which data type is used to store a single character in Java?</h3>
            <div class="options">
                <label class="option">
                    <input type="radio" name="q2" value="a">
                    String
                </label>
                <label class="option">
                    <input type="radio" name="q2" value="b">
                    char
                </label>
                <label class="option">
                    <input type="radio" name="q2" value="c">
                    Character
                </label>
                <label class="option">
                    <input type="radio" name="q2" value="d">
                    int
                </label>
            </div>
        </div>

        <div class="question">
            <h3>Question 3: What does JVM stand for?</h3>
            <div class="options">
                <label class="option">
                    <input type="radio" name="q3" value="a">
                    Java Virtual Machine
                </label>
                <label class="option">
                    <input type="radio" name="q3" value="b">
                    Java Variable Method
                </label>
                <label class="option">
                    <input type="radio" name="q3" value="c">
                    Java Visual Manager
                </label>
                <label class="option">
                    <input type="radio" name="q3" value="d">
                    Java Version Manager
                </label>
            </div>
        </div>

        <button type="submit" class="submit-btn">Submit Assessment</button>
    </form>

    <div id="result" class="result">
        <h3>Assessment Complete!</h3>
        <p id="score"></p>
        <p>Thank you for completing the assessment. Your results have been recorded.</p>
        <a href="/local/orgadmin/student_dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>
</div>

<script>
document.getElementById('assessmentForm').addEventListener('submit', function(e) {
    e.preventDefault();

    // Get answers
    const answers = {
        q1: document.querySelector('input[name="q1"]:checked')?.value,
        q2: document.querySelector('input[name="q2"]:checked')?.value,
        q3: document.querySelector('input[name="q3"]:checked')?.value
    };

    // Check if all questions are answered
    if (!answers.q1 || !answers.q2 || !answers.q3) {
        alert('Please answer all questions before submitting.');
        return;
    }

    // Calculate score (correct answers: a, b, a)
    let score = 0;
    if (answers.q1 === 'a') score++;
    if (answers.q2 === 'b') score++;
    if (answers.q3 === 'a') score++;

    // Hide form and show result
    document.getElementById('assessmentForm').style.display = 'none';
    document.getElementById('result').style.display = 'block';
    document.getElementById('result').className = 'result success';
    document.getElementById('score').textContent = `Your Score: ${score}/3 (${Math.round(score/3*100)}%)`;

    // Log to console (in real app, would send to server)
    console.log('Assessment submitted:', answers, 'Score:', score);
});

// Make options clickable
document.querySelectorAll('.option').forEach(option => {
    option.addEventListener('click', function() {
        const radio = this.querySelector('input[type="radio"]');
        radio.checked = true;
    });
});
</script>

<?php
echo $OUTPUT->footer();
?>