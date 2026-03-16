// This file is part of mod_datalynx for Moodle - http://moodle.org/
//
// It is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// It is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Handles team member selection and subscription functionality in datalynx.
 *
 * @module      mod_datalynx/teammemberselect
 * @copyright   2025 Wunderbyte GmbH
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/toast', 'core/str'], function(Ajax, Toast, Str) {
    return {
        init(datalynxId, fieldId, userurl, userName, canUnsubscribe) {
            const viewContainer = document.querySelectorAll(`[data-id="${datalynxId}"]`);
            Promise.all([
                Str.get_string('subscribe', 'mod_datalynx'),
                Str.get_string('unsubscribe', 'mod_datalynx')
            ]).then(strings => {
                const subscribeString = strings[0];
                const unsubscribeString = strings[1];

                viewContainer.forEach(element => {
                    if (element.getAttribute("data-listeneradded") !== "1") {
                        element.setAttribute("data-listeneradded", "1");
                        document.querySelectorAll('a.datalynxfield_subscribe').forEach(link => {
                            const params = this.extractParams(link.href.split('?')[1]);
                            if (params.fieldid !== fieldId.toString()) {
                                return;
                            }
                            link.addEventListener('click', (e) => {
                                e.preventDefault();
                                const updatedParams = this.extractParams(link.href.split('?')[1]);
                                this.handleSubscription(link, updatedParams, userurl, userName,
                                    canUnsubscribe, subscribeString, unsubscribeString);
                            });
                        });
                    }
                });

            });
        },

        handleSubscription(link, params, userurl, username, canunsubscribe,
                           subscribeString, unsubscribeString) {
            Ajax.call([{
                methodname: 'mod_datalynx_team_subscription',
                args: params,
                done: (response) => {
                    if (response.success) {
                        if (link.classList.contains('subscribed')) {
                            if (canunsubscribe) {
                                this.updateSubscriptionLink(link, 'subscribe', subscribeString);
                                this.removeUserFromList(link.parentElement, userurl);
                            }
                        } else {
                            this.updateSubscriptionLink(link, 'unsubscribe', unsubscribeString);
                            this.addUserToList(link.parentElement, userurl, username);
                        }
                    } else {
                        const errorMessage = response.error;
                        Toast.add(errorMessage);
                    }
                },
                fail: (error) => {
                    const errorMessage = error.message;
                    Toast.add(errorMessage);
                }
            }]);
        },

        updateSubscriptionLink(link, action, text) {
            link.classList.toggle('subscribed');
            link.title = text;
            link.textContent = text;
            link.href = link.href.replace(/(subscribe|unsubscribe)/, action);
        },

        removeUserFromList(linkContainer, userurl) {
            if (linkContainer) {
                const teamMemberList = linkContainer.querySelector('.team-member-list');
                if (teamMemberList) {
                    const listItem = teamMemberList.querySelector(`li a[href="${userurl}"]`);
                    if (listItem) {
                        listItem.closest('li').remove();
                        if (teamMemberList.children.length === 0) {
                            teamMemberList.remove();
                        }
                    }
                }
            }
        },

        addUserToList(linkContainer, userurl, username) {
            let teamMemberList = linkContainer.querySelector('.team-member-list');
            if (!teamMemberList) {
                teamMemberList = document.createElement('ul');
                teamMemberList.classList.add('team-member-list');
                linkContainer.insertBefore(teamMemberList, linkContainer.querySelector("a.datalynxfield_subscribe"));
            }

            const listItem = document.createElement('li');
            listItem.innerHTML = `<a href="${userurl}">${username}</a>`;
            teamMemberList.appendChild(listItem);
        },

        extractParams(paramString) {
            return paramString.split('&').reduce((acc, param) => {
                const [key, value] = param.split('=');
                acc[key] = decodeURIComponent(value || '');
                return acc;
            }, {});
        }
    };
});
