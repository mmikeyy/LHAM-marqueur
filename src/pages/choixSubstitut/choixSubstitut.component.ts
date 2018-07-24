import { Component, OnInit, OnDestroy } from '@angular/core';
import { NavController } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux } from '@angular-redux/store';
import { JoueurMatch, JoueursService } from '../../services/joueursService';
import { IAppState } from '../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';
import { ConfirmerSubstitutComponent } from '../ConfirmerSubstitut/confirmerSubstitut.component';



@Component(
  {
    templateUrl: './choixSubstitut.component.html'
  }
)
export class ChoixSubstitutComponent implements OnInit, OnDestroy {


  public subs: Subscription[] = [];
  public nom: string = '';

  public idsExclus: string[] = [];


  constructor(
    public store: NgRedux<IAppState>,
    public joueursServ: JoueursService,
    public navCtrl: NavController
  ) {

  }

  ngOnInit() {
    this.subs.push();
    this.idsExclus =
      R.pipe(
        R.filter((joueur: JoueurMatch) => joueur.choisi),
        R.map(R.prop('id'))
      ) (this.joueursServ.joueurs) as string[];
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  listeFiltree() {
    if (!this.nom) {
      return [];
    }
    this.nom = this.nom.trim();
    if (!this.nom.match(/^[a-z]/i)) {
      return [];
    }
    let lettre = _.deburr(this.nom.substr(0, 1).toLowerCase());
    this.joueursServ.getSuggestions(lettre);

    return R.prop(lettre, this.joueursServ.suggestions);
  }

  filtrer(candidat) {
    if (!this.nom || this.nom.length === 0 || !R.match(/^[a-z]i.*/, this.nom)) {
      // console.log(candidat, this.nom, 1);
      return false;
    }
    if (R.contains(candidat.id, this.idsExclus)) {
      // console.log(candidat.id, this.idsExclus, 'exclu');
      return false;
    }
    // console.log(candidat.nom_famille.substr(0, this.nom.length), 'vs', _.deburr(this.nom.toLowerCase()));
    return  candidat.nom_famille.substr(0, this.nom.length) === _.deburr(this.nom.toLowerCase());
  }

  info(candidat) {
    this.joueursServ.getDetailsSuggestion(candidat);
    this.navCtrl.push(ConfirmerSubstitutComponent);
  }
}
