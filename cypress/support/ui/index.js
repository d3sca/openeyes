import './examination'
import './eyedraw'

Cypress.Commands.add('getElementByName', (elementName) => {
    return cy.get(`section[data-element-type-name="${elementName}"]`);
});

Cypress.Commands.add('getElementSideByName', (elementName, side) => {
    return cy.getElementByName(elementName).find(`.element-eyes .column[data-side=${side}]`);
});