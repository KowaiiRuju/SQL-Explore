/**
 * events.js - Logic for Events & Scoring page
 * Expects 'scoreMap' and 'teamsData' to be defined globally by the PHP page.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Delete Confirmation
    document.addEventListener('submit', function (e) {
        if (e.target.classList.contains('delete-event-form')) {
            if (!confirm('Delete this event? All scores associated with it will be removed from teams.')) {
                e.preventDefault();
            }
        }
    });
});

function openScoreModal(eventId) {
    const inputId = document.getElementById('scoreEventId');
    if (!inputId) return;
    inputId.value = eventId;

    const eventScores = scoreMap[eventId] || {};

    // Iterate over teamsData to set values
    if (typeof teamsData !== 'undefined') {
        teamsData.forEach(team => {
            const input = document.getElementById(`score_input_${team.id}`);
            if (input) {
                input.value = eventScores[team.id] || 0;
            }
        });
    }

    const modalEl = document.getElementById('scoreModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}

function openPlacementModal(eventId) {
    const inputId = document.getElementById('placementEventId');
    if (inputId) {
        inputId.value = eventId;
    }
    const modalEl = document.getElementById('placementModal');
    if (modalEl) {
        new bootstrap.Modal(modalEl).show();
    }
}
