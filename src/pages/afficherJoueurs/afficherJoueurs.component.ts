import { Component, OnInit, OnDestroy } from '@angular/core';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { JoueurMatch, JoueursService } from '../../services/joueursService';
import { MatchsService } from '../../services/matchs.service';
import { IAppState } from '../../store/store';
import * as R from 'ramda';
import * as _ from 'lodash';

@Component(
  {
    selector: '',
    templateUrl: './afficherJoueurs.component.html'
  }
)
export class AfficherJoueursComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public ids: string[] = [];
  public noms: {[id: string]: string} = {};
  public selectedId: string = '';
  public liste = [];
  public listeId;



  constructor(
    public store: NgRedux<IAppState>,
    public joueursServ: JoueursService,
    public matchsServ: MatchsService
  ) {
    this.joueursServ.initialize();
  }

  ngOnInit() {
    this.subs.push(
      this.joueursServ.joueursRefreshed.subscribe(() => {
        this.prepare();
      })
    );

    if (this.ids.length == 0) {
      this.prepare();
    }

  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  prepare() {
    this.ids = [this.joueursServ.idEquipe1, this.joueursServ.idEquipe2];

    this.noms = {};
    this.noms[this.joueursServ.idEquipe1] = this.matchsServ.nomEq(this.joueursServ.idEquipe1);
    this.noms[this.joueursServ.idEquipe2] = this.matchsServ.nomEq(this.joueursServ.idEquipe2);

    console.log('x',this.joueursServ.idEquipe1, this.joueursServ.idEquipe2, this.joueursServ.joueurs);
  }

  getListe() {
    if (this.listeId === this.selectedId) {
      return this.liste;
    }
    this.liste = R.pipe(
      R.filter((j: JoueurMatch) => (j.id_equipe_match || j.id_equipe) === this.selectedId && j.choisi),
      R.sortBy(val => _.deburr(val.nom.toLowerCase()))
    )(this.joueursServ.joueurs);
    this.listeId = this.selectedId;
    return this.liste;
  }

}
