import type { GraphNode } from './types';

/** A relation seen from one entity, as in "Zeus is <role> Apollo". */
export type Role = 'child' | 'sibling' | 'consort' | 'coRuler' | 'parent' | 'councilMember' | 'councilHead' | 'equivalent';

/** Display order in the details panel. */
export const ROLE_ORDER: Role[] = ['child', 'sibling', 'consort', 'coRuler', 'parent', 'councilMember', 'councilHead', 'equivalent'];

type Gendered = [male: string, female: string, neutral: string];

const ROLE_LABELS: Record<Role, Gendered | string> = {
    child: ['Son of', 'Daughter of', 'Child of'],
    sibling: ['Brother of', 'Sister of', 'Sibling of'],
    consort: ['Husband of', 'Wife of', 'Consort of'],
    coRuler: 'Co-ruler with',
    parent: ['Father of', 'Mother of', 'Parent of'],
    councilMember: 'Member of the council/court of',
    councilHead: 'Council/court includes',
    equivalent: 'Equivalent of',
};

function gendered(words: Gendered, node: GraphNode | undefined): string {
    const gender = node?.gender?.value;

    return gender === 'male' ? words[0] : gender === 'female' ? words[1] : words[2];
}

/** "Son of", "Wife of", "Brother of"… worded for the entity's own gender. */
export function roleLabel(role: Role, node: GraphNode): string {
    const label = ROLE_LABELS[role];

    return typeof label === 'string' ? label : gendered(label, node);
}

/** The word for a child on a parent → child line: son, daughter or child. */
export function childWord(child: GraphNode | undefined): string {
    return gendered(['son', 'daughter', 'child'], child);
}

/**
 * The words for a pair drawn side by side, left one first: "husband · wife",
 * "brother · sister". Two of the same gender read "wives", "brothers"…;
 * unknown genders fall back to the neutral word.
 */
export function pairWords(kind: 'consort' | 'sibling', left: GraphNode | undefined, right: GraphNode | undefined): string {
    const words: Record<typeof kind, { male: string; female: string; plural: Gendered }> = {
        consort: { male: 'husband', female: 'wife', plural: ['husbands', 'wives', 'consorts'] },
        sibling: { male: 'brother', female: 'sister', plural: ['brothers', 'sisters', 'siblings'] },
    };
    const { male, female, plural } = words[kind];
    const genders = [left?.gender?.value, right?.gender?.value];

    if (genders.every((gender) => gender === 'male')) {
        return plural[0];
    }

    if (genders.every((gender) => gender === 'female')) {
        return plural[1];
    }

    if (genders.every((gender) => gender === 'male' || gender === 'female')) {
        return genders.map((gender) => (gender === 'male' ? male : female)).join(' · ');
    }

    return plural[2];
}
