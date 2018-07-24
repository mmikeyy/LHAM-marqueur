import { EventEmitter, Injectable } from '@angular/core';
import { NgRedux } from '@angular-redux/store/lib/src';
import { MatchsActions } from '../store/matchs/matchs.actions';

import { IAppState } from '../store/store';
import { XhrService } from './xhr.service';
import * as R from 'ramda';
import * as _ from 'lodash';

interface MatchRecent {
  id_match: string;
  nom_equipe: string;
  date: string;
  lieu: string;
}

interface CompteMatchsSaisons {
  nom_saison: string;
  nb: number;
}

interface Suggestion {
  id: string;
  nom: string;
  nom_famille: string;
  no_chandail: number;
  position: number;
  division: string;
  date_naissance: string;
  courriel: string;
  cell: string;
  matchs_recents?: MatchRecent[];
  compte_matchs_saisons?: CompteMatchsSaisons[];
}

export interface JoueurMatch {
  id: string;
  nom: string;
  no_chandail: number;
  position: number;
  gardien_dj: boolean;
  id_equipe: string;
  id_equipe_match: string;
  choisi: boolean;
  locked: boolean;
  substitut: boolean;
  type: string;
}

@Injectable()
export class JoueursService {

  public idMatch = '';
  public idEquipe1;
  public idEquipe2;
  public refreshing: boolean = false;
  public valid = false;
  public forfait1 = false;
  public forfait2 = false;

  public suggestions: { [lettre: string]: Suggestion[] } = {};

  public idEquipe;

  public joueurs: JoueurMatch[] = [];

  public suggestionChoisie: Suggestion;
  public joueursRefreshed = new EventEmitter();

  constructor(
    public store: NgRedux<IAppState>,
    public xhr: XhrService,
    public matchsAct: MatchsActions
  ) {
  }

  /*
  Toujours appeler #initialize pour s'assurer que le bon match est chargé en mémoire,
  et que la bonne liste de joueurs est aussi chargée.
   */
  async initialize() {
    let state = this.store.getState().mesMatchs;
    let idMatch = state.get('idMatch');
    if (!this.valid || idMatch !== this.idMatch) {
      this.valid = await this.refresh(idMatch);
    } else {
      let match = state.getIn(['liste', idMatch]);
      if (this.idEquipe1 !== match.get('id_equipe1') || this.idEquipe2 !== match.get('id_equipe2')) {
        this.valid = await this.refresh();
      } else {
        this.valid = true;
      }
    }

    if (this.valid) {
      let match = this.store.getState().mesMatchs.getIn(['liste', idMatch]);
      this.idEquipe1 = match.get('id_equipe1');
      this.idEquipe2 = match.get('id_equipe2');
      this.forfait1 = match.get('forfait1');
      this.forfait2 = match.get('forfait2');
    }

  }

  async refresh(idMatch = null) {
    if (!idMatch) {
      idMatch = this.store.getState().mesMatchs.get('idMatch');
    }
    this.joueurs = [];
    this.refreshing = true;
    let xhrData = await this.xhr.get('marqueur', 'get_joueurs', {
      id_match: idMatch,
      raw_list: true,
      process_list: true
    }, {displayError: true, noThrow: true});
    if (xhrData.result) {

      this.idMatch = idMatch;
      this.joueurs = xhrData.liste;
      this.refreshing = false;


      setTimeout(() =>this.joueursRefreshed.emit());
      return true;
    } else {
      this.refreshing = false;
      return false;
    }
  }

  async choisirEquipe(idEquipe) {
    await this.initialize();
    if (idEquipe !== this.idEquipe1 && idEquipe !== this.idEquipe2) {
      return false;
    }
    this.idEquipe = idEquipe;
    return true;
  }


  getSuggestions(lettre: string, force = false) {
    if (R.has(lettre, this.suggestions) && !force) {
      return
    }
    this.suggestions = R.assoc(lettre, [], this.suggestions);
    this.xhr.get('marqueur', 'get_substituts', {lettre}, {noThrow: true, displayError: true})
      .then(data => {
        if (data.result) {

          this.suggestions = R.assoc(lettre, data.liste.map(item => {
            item.nom = _.deburr(item.nom);
            return item;
          }), this.suggestions);
        }
      });
  }

  mergeSuggestion(suggestion: Suggestion, vals) {
    let lettre = _.deburr(suggestion.nom.substr(0, 1).toLowerCase());
    let index = R.findIndex((sugg: Suggestion) => sugg.id === suggestion.id, this.suggestions[lettre]);
    if (index < 0) {
      return;
    }
    let current = this.suggestions[lettre][index];
    let updatedSuggestion = R.merge(current, vals);
    this.suggestions = R.assoc(lettre, R.update(index, updatedSuggestion, this.suggestions[lettre]), this.suggestions);
    this.suggestionChoisie = updatedSuggestion;
  }

  getDetailsSuggestion(suggestion: Suggestion) {
    this.suggestionChoisie = suggestion;
    if (suggestion.matchs_recents) {
      return;
    }
    this.mergeSuggestion(suggestion, {matchs_recents: [], compte_matchs_saisons: []});
    this.xhr.get('marqueur', 'info_membre_marqueur', {
      id_membre: suggestion.id,
      id_match: this.idMatch
    }, {noThrow: true, displayError: true})
      .then(data => {
        if (data.result) {
          this.mergeSuggestion(suggestion, R.pick(['matchs_recents', 'compte_matchs_saisons'], data));
        }
      });
  }

  confirmerSubstitut() {
    let index = R.findIndex(j => j.id === this.suggestionChoisie.id, this.joueurs);
    if (index > -1) {
      let joueur = this.joueurs[index];
      let update = {
        choisi: true,
        substitut: true,
        id_equipe_match: (this.idEquipe !== joueur.id_equipe ? this.idEquipe : null)
      };
      this.joueurs = R.update(index, R.merge(joueur, update), this.joueurs);
      console.log('joueurs', this.joueurs);
      return;
    }
    let nouveau = R.pick(['id', 'nom', 'no_chandail', 'position'], this.suggestionChoisie) as any;

    nouveau = R.merge(nouveau, {
      id_equipe: null,
      id_equipe_match: this.idEquipe,
      choisi: true,
      substitut: true,
      locked: false,
      type: ''
    }) as JoueurMatch;

    this.ajouterAJoueurs(nouveau);
    console.log('joueurs', this.joueurs);
  }

  ajouterAJoueurs(nouveau: JoueurMatch) {
    this.joueurs.push(nouveau);

    this.joueurs = R.sortBy(val => _.deburr(val.nom).toLowerCase(), this.joueurs);
  }

  ajouterASuggestions(nouveau: Suggestion) {
    let lettre = _.deburr(nouveau.nom.substr(0, 1)).toLowerCase();
    if (!R.has(lettre, this.suggestions)) {
      return;
    }
    // R.sortBy(R.prop('nom_famille'), R.append(nouveau, this.suggestions[lettre]))
    this.suggestions = R.assoc(
      lettre,
      R.pipe(
        R.prop('nom_famille'),
        R.append(nouveau),
        R.sortBy(R.prop('nom_famille'))
      )(this.suggestions),
      this.suggestions)
  }

  nouveauJoueur(vals) {
    vals = R.merge(vals, {
      id_match: this.idMatch
    });
    return this.xhr.post('gestion_membres', 'creation_par_marqueur', vals, {noThrow: true, displayError: true})
      .then(data => {
        if (data.result) {
          let joueur: JoueurMatch = {
            id: data.id,
            nom: vals.nom + ', ' + vals.prenom,
            no_chandail: R.propOr(null, 'no_chandail', vals),
            position: -1,
            gardien_dj: false,
            id_equipe: null,
            id_equipe_match: this.idEquipe,
            choisi: true,
            locked: false,
            substitut: true,
            type: 'ajout'
          };
          this.ajouterAJoueurs(joueur);
          let sugg: Suggestion = {
            id: data.id,
            nom: vals.nom + ', ' + vals.prenom,
            nom_famille: _.deburr(vals.nom).toLowerCase(),
            no_chandail: null,
            position: -1,
            division: '',
            date_naissance: vals.date_naissance,
            courriel: vals.courriel,
            cell: vals.cell
          };
          this.ajouterASuggestions(sugg);
          return true;
        } else {
          return false;
        }
      })


  }

  soumettreJoueurs() {
    let choix = {};
    for (let idEquipe of [this.idEquipe1, this.idEquipe2]) {
      choix = R.assoc(idEquipe, R.pipe(
        R.filter((val: JoueurMatch) => val.choisi && (val.id_equipe_match || val.id_equipe) === idEquipe),
        R.map(R.pick(['id', 'position', 'no_chandail']))
        )(this.joueurs),
        choix);
    }

    return this.xhr.post('marqueur', 'choisir_joueurs_match', {
      id_match: this.idMatch,
      choix: JSON.stringify(choix)
    }, {noThrow: true, displayError: true})
      .then(
        data => {
          if (data.result) {
            if (this.store.getState().mesMatchs.get('idMatchCharge') === this.idMatch) {
              this.matchsAct.merge(
                {
                  joueurs: R.pipe(
                    R.filter(R.prop('choisi')),
                    R.sortBy(j => _.deburr(j.nom.toLowerCase())),
                    R.map(
                      R.pipe(
                        R.pick([
                          'id',
                          'nom',
                          'position',
                          'no_chandail'
                        ]),
                        (joueur: any) =>
                          R.merge({
                            id_match: this.idMatch,
                            id_equipe: joueur.id_equipe_match || joueur.id_equipe,
                            type: ''
                          })(joueur)
                      )
                    )
                  )(this.joueurs)
                }
              )
            }
          }
          return !!data.result;
        }
      )

  }

  /*
  id: string;
  nom: string;
  nom_famille: string;
  no_chandail: number;
  position: number;
  division: string;
  date_naissance: string;
  courriel: string;
  cell: string;
  matchs_recents?: MatchRecent[];
  compte_matchs_saisons?: CompteMatchsSaisons[];

  id: string;
  nom: string;
  no_chandail: string;
  position: number;
  id_equipe: string;
  id_equipe_match: string;
  choisi: boolean;
  locked: boolean;
  substitut: boolean;
  type: string;
   */
}