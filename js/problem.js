/**
 * Provides the javascript for problem reporting.
 *
 * @copyright  2014-2015 Horde LLC
 * @license    LGPL-2 (http://www.horde.org/licenses/lgpl)
 */

var HordeProblem = {

    onSubmit: function(e)
    {
        var subject = document.getElementById('subject'),
            message = document.getElementById('message');
        if (subject.value === '') {
            window.alert(this.summary_text);
            subject.focus();
            e.preventDefault();
        } else if (message.value === '') {
            window.alert(this.message_text);
            message.focus();
            e.preventDefault();
        } else {
            document.getElementById('actionID').value = 'send_problem_report';
        }
    }

};

document.getElementById('problem-report').addEventListener('click', HordeProblem.onSubmit.bind(HordeProblem));
