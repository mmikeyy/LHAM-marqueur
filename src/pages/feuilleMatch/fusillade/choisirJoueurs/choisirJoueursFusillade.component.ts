import { Component, EventEmitter, OnInit, OnDestroy } from '@angular/core';
import { NavParams, reorderArray } from 'ionic-angular';
import { Subscription } from 'rxjs';
import { NgRedux, select } from '@angular-redux/store';
import { FmService } from '../../../../services/fmService';
import { Fusillade, JoueurMatch } from '../../../../store/mesMatchs/mesMatchs.types';
import { IAppState } from '../../../../store/store';
import * as _ from 'lodash';
import * as R from 'ramda';
import { compact } from '../../../../types/types';


@Component(
  {
    selector: '',
    templateUrl: './choisirJoueursFusillade.component.html'
  }
)
export class ChoisirJoueursFusilladeComponent implements OnInit, OnDestroy {

  public subs: Subscription[] = [];
  public ronde: number;
  public joueurs: JoueurMatch[] = [];
  public liste: Fusillade[] = [];
  public selectionnes: string[][] = [[], []];


  @select(['mesMatchs', 'joueurs']) $joueurs;
  @select(['mesMatchs', 'fusillade']) $fusillade;

  constructor(
    public store: NgRedux<IAppState>,
    public params: NavParams,
    public fmServ: FmService
  ) {
    this.ronde = this.params.get('ronde');
  }

  ngOnInit() {
    this.subs.push(
      this.$joueurs.subscribe(joueurs => {
        this.joueurs = joueurs.toJS();
      }),
      this.$fusillade.subscribe(fusillade => {
          this.liste = fusillade.filter(ev => ev.get('ronde') === this.ronde).toJS()
        }
      ),
      this.$joueurs
        .merge(this.$fusillade)
        .debounceTime(100)
        .subscribe(() => {
          let fn = (fld) => R.pipe(R.pluck(fld), R.filter(a => R.identity(a) as any));
          this.selectionnes = [
            fn('id_joueur1')(this.liste) as string[],
            fn('id_joueur2')(this.liste) as string[]
          ];

        })
    )
  }

  ngOnDestroy() {
    this.subs.forEach(s => s.unsubscribe());
  }

  joueursEq(idEquipe) {
    return j => j.position !== 2 && j.id_equipe === idEquipe
  }
  reorderItems(ind, indexes) {
    this.selectionnes[ind] = reorderArray(this.selectionnes[ind], indexes);
  }
}
